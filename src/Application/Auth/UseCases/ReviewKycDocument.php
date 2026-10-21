<?php

namespace App\Application\Auth\UseCases;

use App\Domain\Auth\Repositories\DocumentRepositoryInterface;
use App\Domain\Vehicles\Repositories\VehicleRepositoryInterface;
use DateTimeImmutable;
use Exception;

class ReviewKycDocument
{
    public function __construct(
        private DocumentRepositoryInterface $documentRepository,
        private ?VehicleRepositoryInterface $vehicleRepository = null
    ) {}

    /**
     * Lista documentos KYC para revisión del administrador (por defecto los PENDING).
     *
     * @return list<array<string, mixed>>
     */
    public function listByStatus(?string $status = 'PENDING'): array
    {
        $docs = $this->documentRepository->findByStatus($status);
        $result = [];

        foreach ($docs as $doc) {
            $result[] = [
                'id'                => $doc->getId(),
                'documentable_type' => $doc->getDocumentableType(),
                'documentable_id'   => $doc->getDocumentableId(),
                'document_type'     => $doc->getDocumentType()->getValue(),
                'file_path'         => $doc->getFilePath(),
                'extracted_data'    => $doc->getExtractedData(),
                'status'            => $doc->getStatus()->getValue(),
                'created_at'        => $doc->getCreatedAt()->format('Y-m-d H:i:s'),
                'updated_at'        => $doc->getUpdatedAt()?->format('Y-m-d H:i:s'),
            ];
        }

        return $result;
    }

    /**
     * Aprueba (VERIFIED) o rechaza (REJECTED) un documento KYC subido por un usuario.
     * Si el documento corresponde a un vehículo y es aprobado, marca el vehículo como verificado y activo.
     *
     * @return array<string, mixed>
     */
    public function execute(
        int $documentId,
        string $rawDecision,
        ?string $reason = null,
        ?int $adminUserId = null
    ): array {
        $doc = $this->documentRepository->findById($documentId);
        if ($doc === null) {
            throw new Exception('El documento solicitado no existe.', 404);
        }

        $normalized = strtoupper(trim($rawDecision));
        $isApproved = in_array($normalized, ['VERIFIED', 'APPROVED', 'APPROVE', 'ACCEPT', 'ACCEPTED'], true);
        $isRejected = in_array($normalized, ['REJECTED', 'REJECT', 'DENIED', 'DENY'], true);

        if (!$isApproved && !$isRejected) {
            throw new Exception('El estado de revisión debe ser VERIFIED (aprobar) o REJECTED (rechazar).', 422);
        }

        $extractedData = $doc->getExtractedData() ?? [];
        $extractedData['reviewed_by_admin_id'] = $adminUserId;
        $extractedData['reviewed_at'] = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        if ($reason !== null && trim($reason) !== '') {
            $extractedData['review_reason'] = trim($reason);
        }
        $doc->setExtractedData($extractedData);

        $vehicleVerified = null;
        if ($isApproved) {
            $doc->verify();

            if ($doc->getDocumentableType() === 'vehicle' && $this->vehicleRepository !== null) {
                $vehicle = $this->vehicleRepository->findById($doc->getDocumentableId());
                if ($vehicle !== null) {
                    $vehicle->verify();
                    $this->vehicleRepository->save($vehicle);
                    $vehicleVerified = true;
                }
            }
        } else {
            $doc->reject();

            if ($doc->getDocumentableType() === 'vehicle' && $this->vehicleRepository !== null) {
                $vehicle = $this->vehicleRepository->findById($doc->getDocumentableId());
                if ($vehicle !== null) {
                    $vehicle->unverify();
                    $this->vehicleRepository->save($vehicle);
                    $vehicleVerified = false;
                }
            }
        }

        $this->documentRepository->save($doc);

        return [
            'id'                => $doc->getId(),
            'documentable_type' => $doc->getDocumentableType(),
            'documentable_id'   => $doc->getDocumentableId(),
            'document_type'     => $doc->getDocumentType()->getValue(),
            'status'            => $doc->getStatus()->getValue(),
            'vehicle_verified'  => $vehicleVerified,
            'extracted_data'    => $doc->getExtractedData(),
            'updated_at'        => $doc->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}
