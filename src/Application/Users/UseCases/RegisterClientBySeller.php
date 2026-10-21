<?php

namespace App\Application\Users\UseCases;

use App\Application\Users\DTOs\UserResponseDto;
use App\Domain\Auth\Entities\Document;
use App\Domain\Auth\Entities\Role;
use App\Domain\Auth\Enums\DocumentStatus;
use App\Domain\Auth\Enums\DocumentType;
use App\Domain\Auth\Exceptions\DocumentExtractionException;
use App\Domain\Auth\Repositories\DocumentRepositoryInterface;
use App\Domain\Auth\Services\DocumentExtractorInterface;
use App\Domain\Auth\ValueObjects\Password;
use App\Domain\Shared\Services\MailerInterface;
use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Shared\ValueObjects\Uuid;
use App\Domain\Users\Entities\User;
use App\Domain\Users\Enums\UserRole;
use App\Domain\Users\Repositories\UserRepositoryInterface;
use App\Domain\Users\ValueObjects\BirthDate;
use App\Domain\Users\ValueObjects\Email;
use App\Domain\Users\ValueObjects\Names;
use App\Domain\Users\ValueObjects\NationalId;
use App\Domain\Users\ValueObjects\Phone;
use App\Domain\Users\ValueObjects\Surnames;
use App\Infrastructure\Database\Redis;
use App\Infrastructure\Mail\UbikEmailTemplates;
use DateTimeImmutable;
use Exception;
use Throwable;

class RegisterClientBySeller
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private DocumentExtractorInterface $documentExtractor,
        private ?DocumentRepositoryInterface $documentRepository = null,
        private ?MailerInterface $mailer = null,
        private ?Redis $redis = null
    ) {}

    /**
     * Registra un nuevo cliente desde el módulo de Vendedoras (vía OCR de cédula o formulario),
     * marcando su Cédula de Identidad directamente como VERIFIED (revisada de antemano por la vendedora),
     * generando una contraseña aleatoria segura y enviando un correo de bienvenida con fallo silencioso.
     *
     * @return array<string, mixed>
     */
    public function execute(
        string $rawEmail,
        string $rawPhone,
        ?string $documentFilePath = null,
        ?string $rawNationalId = null,
        ?string $rawNames = null,
        ?string $rawSurnames = null,
        ?string $rawBirthDate = null,
        string $sellerName = 'Asesor Comercial UbikGo'
    ): array {
        // 1. Validar formato de Correo y Teléfono
        $email = new Email($rawEmail);
        $phone = new Phone($rawPhone);

        // 2. Verificar que el correo no esté registrado previamente
        if ($this->userRepository->findByEmail($email) !== null) {
            throw new Exception('El correo electrónico ya se encuentra registrado.', 409);
        }

        // 3. Extraer datos de la cédula mediante OCR si se adjuntó imagen
        $extractedIdentity = null;
        $hasDocFile = !empty($documentFilePath) && file_exists($documentFilePath);

        if ($hasDocFile) {
            try {
                $extractedIdentity = $this->documentExtractor->extractIdentityCard((string)$documentFilePath);
            } catch (DocumentExtractionException $e) {
                if (empty($rawNationalId) || empty($rawNames) || empty($rawSurnames)) {
                    throw $e;
                }
            }
        } elseif (empty($rawNationalId) || empty($rawNames) || empty($rawSurnames)) {
            throw new Exception(
                'Debe adjuntar la foto de la cédula del cliente o ingresar cédula, nombres y apellidos.',
                422
            );
        }

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
            throw new Exception('Los datos patronímicos (cédula, nombres y apellidos) son obligatorios.', 422);
        }

        // 4. Comprobar unicidad de Cédula de Identidad
        if ($this->userRepository->findByIdentificationCard($nationalId) !== null) {
            throw new Exception('Ya existe un cliente registrado con la cédula ingresada.', 409);
        }

        // 5. Generar contraseña aleatoria que cumple estrictamente con el Value Object Password
        $plainPassword = Password::generateRandomPlainText(10);
        $password = Password::fromPlainText($plainPassword);

        // 6. Construir y persistir el Usuario con rol 'client'
        $user = new User(
            id: null,
            uuid: Uuid::generate(),
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
            consentAt: new DateTimeImmutable(),
            deletedAt: null,
            isActive: true,
            roles: [new Role(null, UserRole::Client->getValue(), 'api')],
            permissions: []
        );

        $this->userRepository->save($user);

        // 7. Guardar el documento de Cédula directamente como VERIFIED (revisado por la vendedora)
        if ($this->documentRepository !== null && $user->getId() !== null) {
            $doc = Document::create(
                documentableType: 'user',
                documentableId: (int)$user->getId(),
                documentType: DocumentType::IdentityCard,
                filePath: $hasDocFile ? (string)$documentFilePath : 'verified_by_seller_office',
                extractedData: [
                    'names'              => $names->getValue(),
                    'surnames'           => $surnames->getValue(),
                    'national_id'        => $nationalId->getValue(),
                    'birth_date'         => $birthDate?->format('Y-m-d'),
                    'verified_by_seller' => true,
                    'seller_name'        => $sellerName,
                ],
                status: DocumentStatus::Verified
            );
            $this->documentRepository->save($doc);
        }

        // 8. Actualizar caché de búsqueda por cédula en Redis
        if ($this->redis !== null && $user->getId() !== null) {
            try {
                $cacheKey = 'seller:client_lookup:' . $nationalId->getValue();
                $this->redis->execute('SET', $cacheKey, (string)$user->getId());
                $this->redis->execute('EXPIRE', $cacheKey, 600);
            } catch (Throwable) {
                // Ignorar fallo de Redis
            }
        }

        // 9. Enviar correo de bienvenida con credenciales (fallo silencioso si SMTP falla)
        $emailSent = false;
        if ($this->mailer !== null) {
            try {
                $subject = '¡Bienvenido a UbikGo! Tus credenciales de acceso';
                $html = UbikEmailTemplates::sellerClientWelcome(
                    fullName: $user->getFullName(),
                    email: $email->getValue(),
                    plainPassword: $plainPassword,
                    sellerName: $sellerName
                );
                $emailSent = $this->mailer->send($email->getValue(), $subject, $html);
            } catch (Throwable $e) {
                error_log('Error silencioso enviando correo de bienvenida al cliente: ' . $e->getMessage());
                $emailSent = false;
            }
        }

        return [
            'client'              => UserResponseDto::fromEntity($user)->toArray(),
            'kyc_identity_status' => DocumentStatus::Verified->getValue(),
            'email_sent'          => $emailSent,
        ];
    }
}
