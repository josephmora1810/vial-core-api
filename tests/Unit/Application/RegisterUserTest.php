<?php

namespace Tests\Unit\Application;

use App\Application\Auth\UseCases\RegisterUser;
use App\Core\JwtService;
use App\Domain\Auth\Exceptions\DocumentExtractionException;
use App\Domain\Users\ValueObjects\Email;
use Exception;
use PHPUnit\Framework\TestCase;
use Tests\Doubles\FakeDocumentExtractor;
use Tests\Doubles\InMemoryPersonalAccessTokenRepository;
use Tests\Doubles\InMemoryUserRepository;

class RegisterUserTest extends TestCase
{
    private InMemoryUserRepository $userRepository;
    private InMemoryPersonalAccessTokenRepository $tokenRepository;
    private FakeDocumentExtractor $documentExtractor;
    private JwtService $jwtService;
    private RegisterUser $useCase;
    private string $dummyFile;

    protected function setUp(): void
    {
        $this->userRepository = new InMemoryUserRepository();
        $this->tokenRepository = new InMemoryPersonalAccessTokenRepository($this->userRepository);
        $this->documentExtractor = new FakeDocumentExtractor();
        $this->jwtService = new JwtService();
        $this->useCase = new RegisterUser(
            $this->userRepository,
            $this->documentExtractor,
            $this->jwtService,
            $this->tokenRepository
        );

        // Crear un archivo temporal real para simular la subida
        $this->dummyFile = tempnam(sys_get_temp_dir(), 'test_cedula_');
        file_put_contents($this->dummyFile, 'fake_image_bytes');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->dummyFile)) {
            @unlink($this->dummyFile);
        }
    }

    public function test_user_can_register_successfully_with_valid_data(): void
    {
        $result = $this->useCase->execute(
            rawEmail: 'conductor@ubikgo.com',
            rawPassword: 'Password123!',
            rawPhone: '+584121234567',
            rawRole: 'driver',
            termsAccepted: true,
            documentFilePath: $this->dummyFile
        );

        // 1. Debe retornar datos del usuario, token y refresh_token
        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('refresh_token', $result);
        $this->assertNotEmpty($result['refresh_token']);

        // 2. Datos patronímicos deben coincidir con la extracción de la cédula
        $user = $result['user'];
        $this->assertSame('ANDREÍNA SOFÍA', $user['names']);
        $this->assertSame('QUINTERO BASTIDAS', $user['surnames']);
        $this->assertSame('V-80123456', $user['identification_card']);
        $this->assertSame('conductor@ubikgo.com', $user['email']);
        $this->assertSame('driver', $user['role']);

        // 3. El usuario debe estar guardado en el repositorio
        $savedUser = $this->userRepository->findByEmail(new Email('conductor@ubikgo.com'));
        $this->assertNotNull($savedUser);
        $this->assertSame($user['uuid'], $savedUser->getUuid()->getValue());

        // 4. El token JWT debe ser válido y contener el UUID y rol correcto
        $payload = $this->jwtService->validateToken($result['token']);
        $this->assertSame($user['uuid'], $payload['sub']);
        $this->assertSame('driver', $payload['role']);
    }

    public function test_registration_fails_if_terms_are_not_accepted(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Debe aceptar los términos, condiciones y políticas de privacidad.");

        $this->useCase->execute(
            rawEmail: 'cliente@ubikgo.com',
            rawPassword: 'Password123!',
            rawPhone: '+584121234567',
            rawRole: 'client',
            termsAccepted: false, // No aceptó términos
            documentFilePath: $this->dummyFile
        );
    }

    public function test_registration_fails_if_user_tries_to_register_as_admin(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("No está permitido registrarse como administrador.");

        $this->useCase->execute(
            rawEmail: 'hacker@ubikgo.com',
            rawPassword: 'Password123!',
            rawPhone: '+584121234567',
            rawRole: 'admin', // Intento de registrarse como admin
            termsAccepted: true,
            documentFilePath: $this->dummyFile
        );
    }

    public function test_registration_fails_if_email_is_already_registered(): void
    {
        // Primer registro exitoso
        $this->useCase->execute(
            rawEmail: 'duplicado@ubikgo.com',
            rawPassword: 'Password123!',
            rawPhone: '+584121234567',
            rawRole: 'client',
            termsAccepted: true,
            documentFilePath: $this->dummyFile
        );

        // Segundo registro con el mismo email
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("El correo electrónico ya se encuentra registrado.");

        $this->useCase->execute(
            rawEmail: 'duplicado@ubikgo.com',
            rawPassword: 'Password123!',
            rawPhone: '+584149876543',
            rawRole: 'client',
            termsAccepted: true,
            documentFilePath: $this->dummyFile
        );
    }

    public function test_registration_fails_if_document_file_does_not_exist(): void
    {
        $this->expectException(DocumentExtractionException::class);

        $this->useCase->execute(
            rawEmail: 'sinfoto@ubikgo.com',
            rawPassword: 'Password123!',
            rawPhone: '+584121234567',
            rawRole: 'client',
            termsAccepted: true,
            documentFilePath: '/ruta/inexistente/no_existe.jpg'
        );
    }

    public function test_registration_fails_if_ocr_fails_to_extract(): void
    {
        $failingExtractor = new FakeDocumentExtractor(shouldFail: true);
        $failingUseCase = new RegisterUser(
            $this->userRepository,
            $failingExtractor,
            $this->jwtService,
            $this->tokenRepository
        );

        $this->expectException(DocumentExtractionException::class);
        $this->expectExceptionMessage("La imagen no es legible o es inválida.");

        $failingUseCase->execute(
            rawEmail: 'ilegible@ubikgo.com',
            rawPassword: 'Password123!',
            rawPhone: '+584121234567',
            rawRole: 'client',
            termsAccepted: true,
            documentFilePath: $this->dummyFile
        );
    }

    public function test_registration_allows_manual_correction_of_patronymics(): void
    {
        $result = $this->useCase->execute(
            rawEmail: 'corregido@ubikgo.com',
            rawPassword: 'Password123!',
            rawPhone: '+584121234567',
            rawRole: 'client',
            termsAccepted: true,
            documentFilePath: $this->dummyFile,
            rawNationalId: 'V-12999998',
            rawNames: 'PEDRO JOSE',
            rawSurnames: 'PEREZ GONZALEZ'
        );

        $user = $result['user'];
        $this->assertSame('V-12999998', $user['identification_card']);
        $this->assertSame('PEDRO JOSE', $user['names']);
        $this->assertSame('PEREZ GONZALEZ', $user['surnames']);
    }

    public function test_registration_fails_if_identification_card_already_exists(): void
    {
        // 1. Primer registro con cédula V-80123456 (del OCR simulado)
        $this->useCase->execute(
            rawEmail: 'usuario1@ubikgo.com',
            rawPassword: 'Password123!',
            rawPhone: '+584121234567',
            rawRole: 'client',
            termsAccepted: true,
            documentFilePath: $this->dummyFile
        );

        // 2. Segundo registro con otro email pero misma cédula
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Ya existe una cuenta registrada con la cédula Ingresada");

        $this->useCase->execute(
            rawEmail: 'usuario2@ubikgo.com',
            rawPassword: 'Password123!',
            rawPhone: '+584149876543',
            rawRole: 'driver',
            termsAccepted: true,
            documentFilePath: $this->dummyFile
        );
    }
}
