<?php

namespace App\Infrastructure\Database\Repositories\Auth;

use App\Domain\Auth\Entities\Document;
use App\Domain\Auth\Enums\DocumentStatus;
use App\Domain\Auth\Enums\DocumentType;
use App\Domain\Auth\Repositories\DocumentRepositoryInterface;
use DateTimeImmutable;
use PDO;

class MariaDbDocumentRepository implements DocumentRepositoryInterface
{
    public function __construct(private PDO $db) {}

    public function save(Document $document): void
    {
        $extractedJson = $document->getExtractedData() !== null
            ? json_encode($document->getExtractedData(), JSON_UNESCAPED_UNICODE)
            : null;

        if ($document->getId() === null) {
            $sql = "
                INSERT INTO documents (
                    documentable_type,
                    documentable_id,
                    document_type,
                    file_path,
                    extracted_data,
                    status,
                    created_at,
                    updated_at
                ) VALUES (
                    :documentable_type,
                    :documentable_id,
                    :document_type,
                    :file_path,
                    :extracted_data,
                    :status,
                    :created_at,
                    :updated_at
                )
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'documentable_type' => $document->getDocumentableType(),
                'documentable_id'   => $document->getDocumentableId(),
                'document_type'     => $document->getDocumentType()->getValue(),
                'file_path'         => $document->getFilePath(),
                'extracted_data'    => $extractedJson,
                'status'            => $document->getStatus()->getValue(),
                'created_at'        => $document->getCreatedAt()->format('Y-m-d H:i:s'),
                'updated_at'        => $document->getUpdatedAt()?->format('Y-m-d H:i:s') ?? $document->getCreatedAt()->format('Y-m-d H:i:s'),
            ]);

            $document->setId((int)$this->db->lastInsertId());
        } else {
            $sql = "
                UPDATE documents SET
                    documentable_type = :documentable_type,
                    documentable_id   = :documentable_id,
                    document_type     = :document_type,
                    file_path         = :file_path,
                    extracted_data    = :extracted_data,
                    status            = :status,
                    updated_at        = :updated_at
                WHERE id = :id
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'id'                => $document->getId(),
                'documentable_type' => $document->getDocumentableType(),
                'documentable_id'   => $document->getDocumentableId(),
                'document_type'     => $document->getDocumentType()->getValue(),
                'file_path'         => $document->getFilePath(),
                'extracted_data'    => $extractedJson,
                'status'            => $document->getStatus()->getValue(),
                'updated_at'        => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
        }
    }

    public function findById(int $id): ?Document
    {
        $stmt = $this->db->prepare("SELECT * FROM documents WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    public function findLatestByEntity(string $documentableType, int $documentableId, DocumentType $documentType): ?Document
    {
        $sql = "
            SELECT * FROM documents 
            WHERE documentable_type = :doc_type 
              AND documentable_id = :doc_id 
              AND document_type = :type 
            ORDER BY id DESC 
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'doc_type' => $documentableType,
            'doc_id'   => $documentableId,
            'type'     => $documentType->getValue(),
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    public function findAllByEntity(string $documentableType, int $documentableId): array
    {
        $sql = "
            SELECT * FROM documents 
            WHERE documentable_type = :doc_type 
              AND documentable_id = :doc_id 
            ORDER BY id DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'doc_type' => $documentableType,
            'doc_id'   => $documentableId,
        ]);

        $docs = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $docs[] = $this->hydrate($row);
        }

        return $docs;
    }

    public function findByStatus(?string $status = 'PENDING'): array
    {
        if ($status !== null && trim($status) !== '') {
            $stmt = $this->db->prepare("
                SELECT * FROM documents
                WHERE status = :status
                ORDER BY id DESC
            ");
            $stmt->execute(['status' => strtoupper(trim($status))]);
        } else {
            $stmt = $this->db->query("SELECT * FROM documents ORDER BY id DESC");
        }

        $docs = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $docs[] = $this->hydrate($row);
        }

        return $docs;
    }

    public function isUserIdentityVerified(int $userId): bool
    {
        // Revisa cédula de identidad obligatoria o pasaporte
        $sql = "
            SELECT 1 FROM documents 
            WHERE documentable_type = 'user' 
              AND documentable_id = :user_id 
              AND document_type IN ('identity_card', 'passport')
              AND status = 'VERIFIED'
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchColumn() !== false;
    }

    public function hasVerifiedVehicleDocument(int $userId): bool
    {
        // Revisa si el usuario tiene al menos un vehículo con carnet de circulación o NIV verificado
        $sql = "
            SELECT 1 
            FROM documents d
            INNER JOIN vehicles v ON d.documentable_type = 'vehicle' AND d.documentable_id = v.id
            WHERE v.user_id = :user_id 
              AND d.document_type IN ('vehicle_registration', 'niv_certificate')
              AND d.status = 'VERIFIED'
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);

        if ($stmt->fetchColumn() !== false) {
            return true;
        }

        // También comprueba si el vehículo mismo fue marcado como is_verified = 1
        $vehicleStmt = $this->db->prepare("
            SELECT 1 FROM vehicles 
            WHERE user_id = :user_id AND is_verified = 1 AND deleted_at IS NULL 
            LIMIT 1
        ");
        $vehicleStmt->execute(['user_id' => $userId]);

        return $vehicleStmt->fetchColumn() !== false;
    }

    private function hydrate(array $row): Document
    {
        $extractedData = !empty($row['extracted_data'])
            ? json_decode($row['extracted_data'], true)
            : null;

        return new Document(
            id: (int)$row['id'],
            documentableType: $row['documentable_type'],
            documentableId: (int)$row['documentable_id'],
            documentType: DocumentType::fromString($row['document_type']),
            filePath: $row['file_path'],
            extractedData: $extractedData,
            status: DocumentStatus::fromString($row['status']),
            createdAt: !empty($row['created_at']) ? new DateTimeImmutable($row['created_at']) : null,
            updatedAt: !empty($row['updated_at']) ? new DateTimeImmutable($row['updated_at']) : null
        );
    }
}
