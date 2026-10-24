<?php

namespace Tests\Unit\Controllers;

use App\Application\Auth\UseCases\{
    GetKycStatus,
    ReviewKycDocument,
    UploadKycDocument
};
use App\Controllers\DocumentController;
use App\Core\{Request, Response};
use App\Domain\Auth\{
    Entities\Role,
    ValueObjects\Password
};
use App\Domain\Shared\ValueObjects\{Money, Uuid};
use App\Domain\Users\Entities\User;
use App\Domain\Users\ValueObjects\{
    BirthDate,
    Email,
    Names,
    NationalId,
    Phone,
    Surnames
};
use DateTimeImmutable;
use Exception;
use PHPUnit\Framework\TestCase;

class DocumentControllerTest extends TestCase
{
    private UploadKycDocument $uploadKycDocument;
    private GetKycStatus $getKycStatus;
    private ReviewKycDocument $reviewKycDocument;
    private DocumentController $controller;

    protected function setUp(): void
    {
        Response::$shouldExit = false;
        $_POST = [];
        $_GET = [];
        $_FILES = [];

        $this->uploadKycDocument = $this->createStub(UploadKycDocument::class);
        $this->getKycStatus = $this->createStub(GetKycStatus::class);
        $this->reviewKycDocument = $this->createStub(ReviewKycDocument::class);

        $this->controller = new DocumentController(
            $this->uploadKycDocument,
            $this->getKycStatus,
            $this->reviewKycDocument
        );
    }

    protected function tearDown(): void
    {
        Response::$shouldExit = true;
        $_POST = [];
        $_GET = [];
        $_FILES = [];
    }

    private function createTestUser(int $id = 1, string $role = 'client'): User
    {
        return new User(
            id: $id,
            uuid: Uuid::generate(),
            names: new Names('Carlos'),
            surnames: new Surnames('Gómez'),
            email: new Email('carlos@example.com'),
            password: Password::fromPlainText('Secret123!'),
            phone: new Phone('+584141234567'),
            walletBalance: new Money(0, 'VES'),
            identificationCard: new NationalId('V-12345678'),
            birthDate: new BirthDate('1990-01-01'),
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
            consentAt: new DateTimeImmutable(),
            deletedAt: null,
            isActive: true,
            roles: [new Role(1, $role)]
        );
    }

    public function testUploadUnauthorized(): void
    {
        $request = new Request();
        ob_start();
        $this->controller->upload($request);
        $output = ob_get_clean();

        $this->assertSame(401, http_response_code());
        $data = json_decode($output, true);
        $this->assertSame('fail', $data['status']);
    }

    public function testUploadMissingDocumentType(): void
    {
        $request = new Request();
        $request->setUser($this->createTestUser());

        ob_start();
        $this->controller->upload($request);
        $output = ob_get_clean();

        $this->assertSame(422, http_response_code());
        $data = json_decode($output, true);
        $this->assertSame('fail', $data['status']);
        $this->assertArrayHasKey('document_type', $data['data']);
    }

    public function testUploadMissingFile(): void
    {
        $_POST['document_type'] = 'identity_card';
        $request = new Request();
        $request->setUser($this->createTestUser());

        ob_start();
        $this->controller->upload($request);
        $output = ob_get_clean();

        $this->assertSame(422, http_response_code());
        $data = json_decode($output, true);
        $this->assertArrayHasKey('document', $data['data']);
    }

    public function testUploadInvalidFileExtension(): void
    {
        $_POST['document_type'] = 'identity_card';
        $_FILES['document'] = [
            'name'     => 'malicious_script.exe',
            'type'     => 'application/octet-stream',
            'tmp_name' => __FILE__,
            'error'    => UPLOAD_ERR_OK,
            'size'     => 1024,
        ];

        $request = new Request();
        $request->setUser($this->createTestUser());

        ob_start();
        $this->controller->upload($request);
        $output = ob_get_clean();

        $this->assertSame(422, http_response_code());
        $data = json_decode($output, true);
        $this->assertStringContainsString('Extensión de archivo no permitida', $data['data']['document']);
    }

    public function testUploadSuccessWithDto(): void
    {
        // Crear un PNG mínimo válido en temp
        $tempPng = tempnam(sys_get_temp_dir(), 'test_png_');
        $img = imagecreatetruecolor(10, 10);
        imagepng($img, $tempPng);

        $_POST['document_type'] = 'identity_card';
        $_FILES['document'] = [
            'name'     => 'cedula.png',
            'type'     => 'image/png',
            'tmp_name' => $tempPng,
            'error'    => UPLOAD_ERR_OK,
            'size'     => filesize($tempPng),
        ];

        $request = new Request();
        $request->setUser($this->createTestUser(10));

        $this->uploadKycDocument
            ->method('execute')
            ->willReturn([
                'id'                => 101,
                'documentable_type' => 'user',
                'documentable_id'   => 10,
                'document_type'     => 'identity_card',
                'file_path'         => 'storage/documents/doc.png',
                'status'            => 'PENDING',
                'extracted_data'    => ['names' => 'CARLOS'],
                'message'           => 'Documento cargado exitosamente.',
            ]);

        ob_start();
        $this->controller->upload($request);
        $output = ob_get_clean();

        if (file_exists($tempPng)) {
            @unlink($tempPng);
        }

        $this->assertSame(201, http_response_code());
        $data = json_decode($output, true);
        $this->assertSame('success', $data['status']);
        $this->assertSame(101, $data['data']['id']);
        $this->assertSame('user', $data['data']['documentable_type']);
        $this->assertSame('identity_card', $data['data']['document_type']);
        $this->assertSame('PENDING', $data['data']['status']);
        $this->assertSame('Documento cargado exitosamente.', $data['data']['message']);
    }

    public function testUploadUseCaseConflictThrows409(): void
    {
        $tempPng = tempnam(sys_get_temp_dir(), 'test_png_');
        $img = imagecreatetruecolor(10, 10);
        imagepng($img, $tempPng);

        $_POST['document_type'] = 'identity_card';
        $_FILES['document'] = [
            'name'     => 'cedula.png',
            'type'     => 'image/png',
            'tmp_name' => $tempPng,
            'error'    => UPLOAD_ERR_OK,
            'size'     => filesize($tempPng),
        ];

        $request = new Request();
        $request->setUser($this->createTestUser(10));

        $this->uploadKycDocument
            ->method('execute')
            ->willThrowException(new Exception('Este documento ya ha sido verificado y aprobado. No es necesario reenviarlo.', 409));

        ob_start();
        $this->controller->upload($request);
        $output = ob_get_clean();

        if (file_exists($tempPng)) {
            @unlink($tempPng);
        }

        $this->assertSame(409, http_response_code());
        $data = json_decode($output, true);
        $this->assertSame('fail', $data['status']);
        $this->assertStringContainsString('ya ha sido verificado', $data['message']);
    }

    public function testStatusUnauthorized(): void
    {
        $request = new Request();
        ob_start();
        $this->controller->status($request);
        $output = ob_get_clean();

        $this->assertSame(401, http_response_code());
    }

    public function testStatusSuccessWithDto(): void
    {
        $request = new Request();
        $request->setUser($this->createTestUser(25));

        $this->getKycStatus
            ->method('execute')
            ->willReturn([
                'user_id'              => 25,
                'is_identity_verified' => true,
                'has_verified_vehicle' => true,
                'is_fully_kyc_passed'  => true,
                'documents'            => [
                    'identity_card'   => ['status' => 'VERIFIED', 'file_path' => 'https://s3/id.jpg'],
                    'passport'        => null,
                    'driving_license' => null,
                ],
            ]);

        ob_start();
        $this->controller->status($request);
        $output = ob_get_clean();

        $this->assertSame(200, http_response_code());
        $data = json_decode($output, true);
        $this->assertSame('success', $data['status']);
        $this->assertSame(25, $data['data']['user_id']);
        $this->assertTrue($data['data']['is_identity_verified']);
        $this->assertTrue($data['data']['has_verified_vehicle']);
        $this->assertTrue($data['data']['is_fully_kyc_passed']);
        $this->assertSame('VERIFIED', $data['data']['documents']['identity_card']['status']);
    }

    public function testAdminIndexWhenServiceNull(): void
    {
        $controller = new DocumentController(
            $this->uploadKycDocument,
            $this->getKycStatus,
            null
        );

        $request = new Request();
        ob_start();
        $controller->adminIndex($request);
        $output = ob_get_clean();

        $this->assertSame(501, http_response_code());
        $data = json_decode($output, true);
        $this->assertSame('error', $data['status']);
    }

    public function testAdminIndexSuccessWithDtoList(): void
    {
        $request = new Request();
        $_GET['status'] = 'ALL';

        $this->reviewKycDocument
            ->method('listByStatus')
            ->willReturn([
                [
                    'id'                => 1,
                    'documentable_type' => 'user',
                    'documentable_id'   => 5,
                    'document_type'     => 'identity_card',
                    'file_path'         => 'storage/documents/doc1.jpg',
                    'extracted_data'    => null,
                    'status'            => 'PENDING',
                    'created_at'        => '2026-10-23 09:00:00',
                    'updated_at'        => null,
                ]
            ]);

        ob_start();
        $this->controller->adminIndex($request);
        $output = ob_get_clean();

        $this->assertSame(200, http_response_code());
        $data = json_decode($output, true);
        $this->assertSame('success', $data['status']);
        $this->assertIsArray($data['data']);
        $this->assertCount(1, $data['data']);
        $this->assertSame(1, $data['data'][0]['id']);
        $this->assertSame('identity_card', $data['data'][0]['document_type']);
    }

    public function testAdminReviewWhenServiceNull(): void
    {
        $controller = new DocumentController(
            $this->uploadKycDocument,
            $this->getKycStatus,
            null
        );

        $request = new Request();
        ob_start();
        $controller->adminReview($request, '1');
        $output = ob_get_clean();

        $this->assertSame(501, http_response_code());
    }

    public function testAdminReviewApproveSuccess(): void
    {
        $_POST['status'] = 'VERIFIED';
        $_POST['reason'] = 'Cédula legible y datos validados contra el SAIME.';

        $request = new Request();
        $request->setUser($this->createTestUser(99, 'admin'));

        $this->reviewKycDocument
            ->method('execute')
            ->willReturn([
                'id'                => 55,
                'documentable_type' => 'vehicle',
                'documentable_id'   => 12,
                'document_type'     => 'vehicle_registration',
                'status'            => 'VERIFIED',
                'vehicle_verified'  => true,
                'extracted_data'    => ['reviewed_by_admin_id' => 99],
                'updated_at'        => '2026-10-23 15:00:00',
            ]);

        ob_start();
        $this->controller->adminReview($request, '55');
        $output = ob_get_clean();

        $this->assertSame(200, http_response_code());
        $data = json_decode($output, true);
        $this->assertSame('success', $data['status']);
        $this->assertSame(55, $data['data']['id']);
        $this->assertSame('VERIFIED', $data['data']['status']);
        $this->assertTrue($data['data']['vehicle_verified']);
    }

    public function testAdminReviewValidationFailure(): void
    {
        $_POST['status'] = 'INVALID_STATUS';

        $request = new Request();
        $request->setUser($this->createTestUser(99, 'admin'));

        $this->reviewKycDocument
            ->method('execute')
            ->willThrowException(new Exception('El estado de revisión debe ser VERIFIED o REJECTED.', 422));

        ob_start();
        $this->controller->adminReview($request, '55');
        $output = ob_get_clean();

        $this->assertSame(422, http_response_code());
        $data = json_decode($output, true);
        $this->assertSame('fail', $data['status']);
    }
}
