<?php

namespace App\Application\UseCases;

use App\Domain\Entities\Document;
use App\Domain\Enums\DocumentStatus;
use App\Domain\Enums\DocumentType;
use App\Domain\Exceptions\DocumentExtractionException;
use App\Domain\Repositories\DocumentRepositoryInterface;
use App\Domain\Repositories\UserRepositoryInterface;
use App\Domain\Services\DocumentExtractorInterface;
use Exception;

class UploadKycDocument
{
    public function __construct(
        private DocumentRepositoryInterface $documentRepository,
        private UserRepositoryInterface $userRepository,
        private ?DocumentExtractorInterface $documentExtractor = null
    ) {}

    /**
     * @param int $userId
     * @param string $rawDocumentType   'identity_card', 'passport', 'vehicle_registration', 'niv_certificate', 'driving_license'
     * @param string $filePath          Ruta al archivo subido
     * @param int|null $vehicleId       Obligatorio si el documento pertenece a un vehículo
     * @return array
     */
    public function execute(
        int $userId,
        string $rawDocumentType,
        string $filePath,
        ?int $vehicleId = null
    ): array {
        // 1. Validar que el usuario exista
        $user = $this->userRepository->findById($userId);
        if ($user === null) {
            throw new Exception("Usuario no encontrado.", 404);
        }

        // 2. Validar tipo de documento
        $docType = DocumentType::fromString($rawDocumentType);

        // 3. Validar existencia del archivo
        if (!file_exists($filePath)) {
            throw new Exception("El archivo del documento no fue encontrado en el servidor.", 400);
        }

        // 4. Determinar si el documento es de usuario o de vehículo
        $isVehicleDoc = in_array($docType, [
            DocumentType::VehicleRegistration,
            DocumentType::NivCertificate
        ], true);

        if ($isVehicleDoc && $vehicleId === null) {
            throw new Exception("Debe especificar el ID del vehículo para este tipo de documento.", 400);
        }

        $documentableType = $isVehicleDoc ? 'vehicle' : 'user';
        $documentableId = $isVehicleDoc ? $vehicleId : $userId;

        // 5. Extracción opcional con OCR para cédulas de identidad
        $extractedData = null;
        if ($docType === DocumentType::IdentityCard && $this->documentExtractor !== null) {
            try {
                $extracted = $this->documentExtractor->extractIdentityCard($filePath);
                $extractedData = [
                    'names'         => $extracted->getNames()->getValue(),
                    'surnames'      => $extracted->getSurnames()->getValue(),
                    'national_id'   => $extracted->getNationalId()->getValue(),
                    'birth_date'    => $extracted->getBirthDate()?->format('Y-m-d'),
                ];
            } catch (DocumentExtractionException) {
                // Si el OCR falla o tiene baja confianza, se mantiene null para revisión manual (FR-33)
                $extractedData = null;
            }
        }

        // 6. Comprobar si ya existe un documento previo de este tipo
        $existingDoc = $this->documentRepository->findLatestByEntity(
            $documentableType,
            $documentableId,
            $docType
        );

        if ($existingDoc !== null) {
            if ($existingDoc->isVerified()) {
                throw new Exception("Este documento ya ha sido verificado y aprobado. No es necesario reenviarlo.", 409);
            }

            // Reenvío de documento rechazado o pendiente: se actualiza y vuelve a PENDING
            $existingDoc->resubmit($filePath, $extractedData);
            $this->documentRepository->save($existingDoc);
            $document = $existingDoc;
        } else {
            // Primer envío del documento
            $document = Document::create(
                documentableType: $documentableType,
                documentableId: $documentableId,
                documentType: $docType,
                filePath: $filePath,
                extractedData: $extractedData,
                status: DocumentStatus::Pending
            );
            $this->documentRepository->save($document);
        }

        return [
            'id'                => $document->getId(),
            'documentable_type' => $document->getDocumentableType(),
            'documentable_id'   => $document->getDocumentableId(),
            'document_type'     => $document->getDocumentType()->getValue(),
            'file_path'         => $document->getFilePath(),
            'status'            => $document->getStatus()->getValue(),
            'extracted_data'    => $document->getExtractedData(),
            'message'           => 'Documento cargado exitosamente. Se encuentra en revisión para validación KYC.'
        ];
    }
}
