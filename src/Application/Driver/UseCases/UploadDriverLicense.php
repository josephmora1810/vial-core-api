<?php

namespace App\Application\Driver\UseCases;

use App\Application\Documents\UseCases\EnqueueDocumentS3Upload;
use App\Domain\Auth\Entities\Document;
use App\Domain\Auth\Enums\{DocumentStatus, DocumentType};
use App\Domain\Auth\Repositories\DocumentRepositoryInterface;
use App\Domain\Users\Enums\UserRole;
use App\Domain\Users\Repositories\UserRepositoryInterface;
use Exception;

class UploadDriverLicense
{
    public function __construct(
        private DocumentRepositoryInterface $documentRepository,
        private UserRepositoryInterface $userRepository,
        private ?EnqueueDocumentS3Upload $enqueueS3 = null
    ) {}

    public function execute(
        int $driverUserId,
        string $filePath,
        ?string $grade = null,
        ?string $expirationDate = null
    ): array {
        $driver = $this->userRepository->findById($driverUserId);
        if ($driver === null) {
            throw new Exception('Operador de grúa no encontrado.', 404);
        }

        if (!$driver->hasRole(UserRole::Driver->getValue()) && !$driver->hasRole(UserRole::Admin->getValue())) {
            throw new Exception('El usuario no posee el rol de conductor u operador de grúas.', 403);
        }

        if (!file_exists($filePath)) {
            throw new Exception('El archivo de la licencia de conducir no fue encontrado.', 400);
        }

        $extractedData = [
            'grade'           => !empty($grade) ? strtoupper(trim($grade)) : '5TO GRADO',
            'expiration_date' => $expirationDate,
            'role'            => 'driver',
        ];

        $existing = $this->documentRepository->findLatestByEntity(
            'user',
            $driverUserId,
            DocumentType::DrivingLicense
        );

        if ($existing !== null) {
            $existing->resubmit($filePath, $extractedData);
            $this->documentRepository->save($existing);
            $doc = $existing;
        } else {
            $doc = Document::create(
                documentableType: 'user',
                documentableId: $driverUserId,
                documentType: DocumentType::DrivingLicense,
                filePath: $filePath,
                extractedData: $extractedData,
                status: DocumentStatus::Pending
            );
            $this->documentRepository->save($doc);
        }

        $docId = (int)$doc->getId();

        // Encolar a AWS S3
        if ($this->enqueueS3 !== null && $docId > 0) {
            $this->enqueueS3->execute(
                documentId: $docId,
                localFilePath: $filePath,
                documentType: DocumentType::DrivingLicense->getValue(),
                documentableType: 'user',
                documentableId: $driverUserId
            );
        }

        return [
            'id'             => $docId,
            'document_type'  => $doc->getDocumentType()->getValue(),
            'file_path'      => $doc->getFilePath(),
            'status'         => $doc->getStatus()->getValue(),
            'extracted_data' => $doc->getExtractedData(),
            'message'        => 'Licencia de conducir cargada exitosamente. Se encuentra en revisión para auditoría KYC.'
        ];
    }
}
