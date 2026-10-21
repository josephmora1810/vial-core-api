<?php

namespace App\Application\Auth\UseCases;

use App\Application\Users\DTOs\UserResponseDto;
use App\Core\JwtService;
use App\Domain\Auth\Entities\PersonalAccessToken;
use App\Domain\Auth\Entities\Role;
use App\Domain\Users\Entities\User;
use App\Domain\Users\Enums\UserRole;
use App\Domain\Auth\Entities\Document;
use App\Domain\Auth\Enums\DocumentStatus;
use App\Domain\Auth\Enums\DocumentType;
use App\Domain\Auth\Exceptions\DocumentExtractionException;
use App\Domain\Auth\Repositories\DocumentRepositoryInterface;
use App\Domain\Auth\Repositories\PersonalAccessTokenRepositoryInterface;
use App\Domain\Users\Repositories\UserRepositoryInterface;
use App\Domain\Auth\Services\DocumentExtractorInterface;
use App\Domain\Users\ValueObjects\BirthDate;
use App\Domain\Users\ValueObjects\Email;
use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Users\ValueObjects\Names;
use App\Domain\Users\ValueObjects\NationalId;
use App\Domain\Auth\ValueObjects\Password;
use App\Domain\Users\ValueObjects\Phone;
use App\Domain\Users\ValueObjects\Surnames;
use App\Domain\Shared\ValueObjects\Uuid;
use DateTimeImmutable;
use Exception;

class RegisterUser
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private DocumentExtractorInterface $documentExtractor,
        private JwtService $jwtService,
        private PersonalAccessTokenRepositoryInterface $tokenRepository,
        private ?DocumentRepositoryInterface $documentRepository = null
    ) {}

    /**
     * @param string $rawEmail
     * @param string $rawPassword
     * @param string $rawPhone
     * @param string $rawRole ('client', 'driver', 'seller')
     * @param bool   $termsAccepted Checkbox de consentimiento obligatorio (FR-03)
     * @param string $documentFilePath Ruta temporal de la imagen de la cédula (FR-07)
     * @param string|null $rawNationalId Corrección manual de cédula (si el OCR erró)
     * @param string|null $rawNames Corrección manual de nombres
     * @param string|null $rawSurnames Corrección manual de apellidos
     * @param string|null $rawBirthDate Corrección manual de fecha de nacimiento (Y-m-d)
     * @return array Datos del usuario creado, access token y refresh token
     */
    public function execute(
        string $rawEmail,
        string $rawPassword,
        string $rawPhone,
        string $rawRole,
        bool $termsAccepted,
        ?string $documentFilePath = null,
        ?string $rawNationalId = null,
        ?string $rawNames = null,
        ?string $rawSurnames = null,
        ?string $rawBirthDate = null
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
        $extractedIdentity = null;
        if (!empty($documentFilePath)) {
            if (!file_exists($documentFilePath)) {
                throw new DocumentExtractionException("El archivo del documento de identidad no fue recibido.", 400);
            }

            try {
                $extractedIdentity = $this->documentExtractor->extractIdentityCard($documentFilePath);
            } catch (DocumentExtractionException $e) {
                // Si el OCR falló y el usuario no suministró datos manuales, propagar error
                if (empty($rawNationalId) || empty($rawNames) || empty($rawSurnames)) {
                    throw $e;
                }
            }
        } elseif (empty($rawNationalId) || empty($rawNames) || empty($rawSurnames)) {
            throw new Exception("Debe adjuntar la imagen del documento de identidad o ingresar los datos patronímicos requeridos.", 400);
        }

        $hasDocFile = !empty($documentFilePath) && file_exists($documentFilePath);

        // 5.1 Resolver patronímicos: Prioridad a datos manuales si fueron provistos (corrección de OCR), sino tomar los del OCR
        $nationalId = !empty($rawNationalId)
            ? new NationalId($rawNationalId)
            : ($extractedIdentity ? $extractedIdentity->getNationalId() : null);

        $names = !empty($rawNames)
            ? new Names($rawNames)
            : ($extractedIdentity ? $extractedIdentity->getNames() : null);

        $surnames = !empty($rawSurnames)
            ? new Surnames($rawSurnames)
            : ($extractedIdentity ? $extractedIdentity->getSurnames() : null);

        $birthDate = !empty($rawBirthDate)
            ? new BirthDate($rawBirthDate)
            : ($extractedIdentity ? $extractedIdentity->getBirthDate() : null);

        if ($nationalId === null || $names === null || $surnames === null) {
            throw new Exception("Los datos patronímicos (cédula, nombres y apellidos) son obligatorios.", 422);
        }

        // 5.2 Comprobar unicidad de Cédula de Identidad en el sistema (1 persona física = 1 cuenta)
        $existingByIdCard = $this->userRepository->findByIdentificationCard($nationalId);
        if ($existingByIdCard !== null) {
            throw new Exception("Ya existe una cuenta registrada con la cédula Ingresada", 409);
        }

        // 6. Hashear la contraseña con Bcrypt mediante su Value Object
        $password = Password::fromPlainText($rawPassword);

        // 7. Generar UUID v7 cronológico y construir la Entidad User
        $uuid = Uuid::generate();

        $user = new User(
            id: null,
            uuid: $uuid,
            names: $names,
            surnames: $surnames,
            email: $email,
            password: $password,
            phone: $phone,
            walletBalance: new Money(0.00, 'VES'),
            identificationCard: $nationalId,
            birthDate: $birthDate,
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

        // 8.1 Persistir documento de identidad inicial para auditoría y flujo KYC (FR-07, FR-10, FR-33)
        if ($this->documentRepository !== null && $user->getId() !== null && $hasDocFile) {
            // Si el usuario modificó manualmente los datos leídos por el OCR, queda PENDING para revisión humana
            // Si coincidió exactamente con el OCR, se aprueba automáticamente VERIFIED
            $isExactMatch = $extractedIdentity !== null
                && $extractedIdentity->getNationalId()->getValue() === $nationalId->getValue()
                && $extractedIdentity->getNames()->getValue() === $names->getValue()
                && $extractedIdentity->getSurnames()->getValue() === $surnames->getValue();

            $docStatus = $isExactMatch ? DocumentStatus::Verified : DocumentStatus::Pending;

            $extractedData = [
                'names'                 => $names->getValue(),
                'surnames'              => $surnames->getValue(),
                'national_id'           => $nationalId->getValue(),
                'birth_date'            => $birthDate?->format('Y-m-d'),
                'was_manual_correction' => !$isExactMatch
            ];

            $doc = Document::create(
                documentableType: 'user',
                documentableId: (int)$user->getId(),
                documentType: DocumentType::IdentityCard,
                filePath: $documentFilePath,
                extractedData: $extractedData,
                status: $docStatus
            );
            $this->documentRepository->save($doc);
        }

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