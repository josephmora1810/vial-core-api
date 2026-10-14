<?php

namespace App\Application\UseCases;

use App\Application\DTOs\UserResponseDto;
use App\Core\JwtService;
use App\Domain\Entities\PersonalAccessToken;
use App\Domain\Entities\Role;
use App\Domain\Entities\User;
use App\Domain\Enums\UserRole;
use App\Domain\Exceptions\DocumentExtractionException;
use App\Domain\Repositories\PersonalAccessTokenRepositoryInterface;
use App\Domain\Repositories\UserRepositoryInterface;
use App\Domain\Services\DocumentExtractorInterface;
use App\Domain\ValueObjects\Email;
use App\Domain\ValueObjects\Money;
use App\Domain\ValueObjects\Password;
use App\Domain\ValueObjects\Phone;
use App\Domain\ValueObjects\Uuid;
use DateTimeImmutable;
use Exception;

class RegisterUser
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private DocumentExtractorInterface $documentExtractor,
        private JwtService $jwtService,
        private PersonalAccessTokenRepositoryInterface $tokenRepository
    ) {}

    /**
     * @param string $rawEmail
     * @param string $rawPassword
     * @param string $rawPhone
     * @param string $rawRole ('client', 'driver', 'seller')
     * @param bool   $termsAccepted Checkbox de consentimiento obligatorio (FR-03)
     * @param string $documentFilePath Ruta temporal de la imagen de la cédula (FR-07)
     * @return array Datos del usuario creado, access token y refresh token
     */
    public function execute(
        string $rawEmail,
        string $rawPassword,
        string $rawPhone,
        string $rawRole,
        bool $termsAccepted,
        string $documentFilePath
    ): array {
        // 1. Consentimiento legal obligatorio (FR-03)
        if (!$termsAccepted) {
            throw new Exception("Debe aceptar los términos, condiciones y políticas de privacidad.", 400);
        }

        // 2. Validar rol solicitado (FR-01) - Prohibir 'admin' por registro público
        $role = UserRole::fromString($rawRole);
        if ($role->isAdmin()) {
            throw new Exception("No está permitido registrarse como administrador.", 403);
        }

        // 3. Validar formato de Correo y Teléfono mediante sus Value Objects
        $email = new Email($rawEmail);
        $phone = new Phone($rawPhone);

        // 4. Verificar que el correo no esté registrado previamente
        if ($this->userRepository->findByEmail($email) !== null) {
            throw new Exception("El correo electrónico ya se encuentra registrado.", 409);
        }

        // 5. Extraer y validar datos patronímicos con el OCR (FR-07)
        if (!file_exists($documentFilePath)) {
            throw new DocumentExtractionException("El archivo del documento de identidad no fue recibido.", 400);
        }

        $extractedIdentity = $this->documentExtractor->extractIdentityCard($documentFilePath);

        // 6. Hashear la contraseña con Bcrypt mediante su Value Object
        $password = Password::fromPlainText($rawPassword);

        // 7. Generar UUID v7 cronológico y construir la Entidad User
        $uuid = Uuid::generate();

        $user = new User(
            id: null,
            uuid: $uuid,
            names: $extractedIdentity->getNames(),
            surnames: $extractedIdentity->getSurnames(),
            email: $email,
            password: $password,
            phone: $phone,
            walletBalance: new Money(0.00, 'VES'),
            identificationCard: $extractedIdentity->getNationalId(),
            birthDate: $extractedIdentity->getBirthDate(),
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
            consentAt: new DateTimeImmutable(), // Registra fecha exacta de aceptación (NFR-21)
            deletedAt: null,
            isActive: true,
            roles: [new Role(null, $role->getValue(), 'api')],
            permissions: []
        );

        // 8. Persistir en MariaDB (inserta usuario y sincroniza rol)
        $this->userRepository->save($user);

        // 9. Emitir Access Token JWT (stateless) para inicio de sesión inmediato (FR-02)
        $token = $this->jwtService->generateToken([
            'sub'   => $user->getUuid()->getValue(),
            'email' => $user->getEmail()->getValue(),
            'role'  => $role->getValue(),
            'name'  => $user->getFullName(),
        ]);

        // 10. Emitir y persistir el Refresh Token (30 días)
        $plainRefreshToken = bin2hex(random_bytes(32));
        $expiresAt = (new DateTimeImmutable())->modify('+30 days');

        $refreshTokenEntity = PersonalAccessToken::create(
            userId: (int)$user->getId(),
            plainToken: $plainRefreshToken,
            expiresAt: $expiresAt
        );
        $this->tokenRepository->save($refreshTokenEntity);

        return [
            'user'          => UserResponseDto::fromEntity($user)->toArray(),
            'token'         => $token,
            'refresh_token' => $plainRefreshToken
        ];
    }
}