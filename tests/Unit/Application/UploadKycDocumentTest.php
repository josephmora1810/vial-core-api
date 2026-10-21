<?php

namespace Tests\Unit\Application;

use App\Application\Auth\UseCases\UploadKycDocument;
use App\Domain\Auth\Entities\Document;
use App\Domain\Users\Entities\User;
use App\Domain\Auth\Enums\DocumentStatus;
use App\Domain\Auth\Enums\DocumentType;
use App\Domain\Users\ValueObjects\BirthDate;
use App\Domain\Users\ValueObjects\Email;
use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Users\ValueObjects\Names;
use App\Domain\Users\ValueObjects\NationalId;
use App\Domain\Auth\ValueObjects\Password;
use App\Domain\Users\ValueObjects\Phone;
use App\Domain\Users\ValueObjects\Surnames;
use App\Domain\Shared\ValueObjects\Uuid;
use Exception;
use PHPUnit\Framework\TestCase;
use Tests\Doubles\InMemoryDocumentRepository;
use Tests\Doubles\InMemoryUserRepository;

class UploadKycDocumentTest extends TestCase
{
    private InMemoryDocumentRepository $docRepo;
    private InMemoryUserRepository $userRepo;
    private UploadKycDocument $useCase;
    private string $tempFile;

    protected function setUp(): void
    {
        $this->docRepo = new InMemoryDocumentRepository();
        $this->userRepo = new InMemoryUserRepository();
        $this->useCase = new UploadKycDocument($this->docRepo, $this->userRepo, null);

        $user = new User(
            id: 1,
            uuid: Uuid::generate(),
            names: new Names('Carlos'),
            surnames: new Surnames('Mendoza'),
            email: new Email('carlos@example.com'),
            password: Password::fromPlainText('Clave123!'),
            phone: new Phone('+584141112233'),
            walletBalance: new Money(0.00, 'VES'),
            identificationCard: new NationalId('V-20111222'),
            birthDate: new BirthDate('1992-03-10')
        );
        $this->userRepo->save($user);

        // Archivo temporal para simular la imagen subida
        $this->tempFile = tempnam(sys_get_temp_dir(), 'test_doc_');
        file_put_contents($this->tempFile, 'dummy image content');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            @unlink($this->tempFile);
        }
    }

    public function test_uploads_new_identity_document_successfully(): void
    {
        $result = $this->useCase->execute(
            userId: 1,
            rawDocumentType: 'identity_card',
            filePath: $this->tempFile
        );

        $this->assertEquals(DocumentStatus::Pending->getValue(), $result['status']);
        $this->assertEquals('identity_card', $result['document_type']);
        $this->assertEquals('user', $result['documentable_type']);
        $this->assertEquals(1, $result['documentable_id']);
    }

    public function test_resubmits_rejected_document_and_resets_to_pending(): void
    {
        // 1. Simular documento previo que fue rechazado
        $oldDoc = Document::create(
            documentableType: 'user',
            documentableId: 1,
            documentType: DocumentType::IdentityCard,
            filePath: '/old/path/doc.jpg',
            status: DocumentStatus::Rejected
        );
        $this->docRepo->save($oldDoc);

        // 2. Reenviar con nueva imagen
        $result = $this->useCase->execute(
            userId: 1,
            rawDocumentType: 'identity_card',
            filePath: $this->tempFile
        );

        $this->assertEquals(DocumentStatus::Pending->getValue(), $result['status']);
        $this->assertEquals($this->tempFile, $result['file_path']);
    }

    public function test_throws_409_when_document_is_already_verified(): void
    {
        $verifiedDoc = Document::create(
            documentableType: 'user',
            documentableId: 1,
            documentType: DocumentType::IdentityCard,
            filePath: '/verified/doc.jpg',
            status: DocumentStatus::Verified
        );
        $this->docRepo->save($verifiedDoc);

        $this->expectException(Exception::class);
        $this->expectExceptionCode(409);
        $this->expectExceptionMessage('Este documento ya ha sido verificado y aprobado');

        $this->useCase->execute(
            userId: 1,
            rawDocumentType: 'identity_card',
            filePath: $this->tempFile
        );
    }

    public function test_requires_vehicle_id_for_vehicle_documents(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('Debe especificar el ID del vehículo');

        $this->useCase->execute(
            userId: 1,
            rawDocumentType: 'vehicle_registration',
            filePath: $this->tempFile,
            vehicleId: null
        );
    }
}
