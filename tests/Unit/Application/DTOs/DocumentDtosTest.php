<?php

namespace Tests\Unit\Application\DTOs;

use App\Application\Documents\DTOs\{
    DocumentResponseDto,
    DocumentS3JobResultDto,
    KycStatusResponseDto
};
use App\Domain\Auth\Entities\Document;
use App\Domain\Auth\Enums\{DocumentStatus, DocumentType};
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class DocumentDtosTest extends TestCase
{
    public function testDocumentResponseDtoFromEntity(): void
    {
        $doc = new Document(
            id: 42,
            documentableType: 'user',
            documentableId: 10,
            documentType: DocumentType::IdentityCard,
            filePath: 'https://s3.amazonaws.com/bucket/doc.jpg',
            extractedData: ['names' => 'JUAN', 'surnames' => 'PEREZ'],
            status: DocumentStatus::Verified,
            createdAt: new DateTimeImmutable('2026-10-20 12:00:00'),
            updatedAt: new DateTimeImmutable('2026-10-21 14:00:00')
        );

        $dto = DocumentResponseDto::fromEntity($doc, vehicleVerified: null, message: 'Aprobado.');

        $this->assertSame(42, $dto->id);
        $this->assertSame('user', $dto->documentable_type);
        $this->assertSame(10, $dto->documentable_id);
        $this->assertSame('identity_card', $dto->document_type);
        $this->assertSame('VERIFIED', $dto->status);
        $this->assertSame('https://s3.amazonaws.com/bucket/doc.jpg', $dto->file_path);
        $this->assertSame('JUAN', $dto->extracted_data['names']);
        $this->assertNull($dto->vehicle_verified);
        $this->assertSame('Aprobado.', $dto->message);
        $this->assertSame('2026-10-20 12:00:00', $dto->created_at);
        $this->assertSame('2026-10-21 14:00:00', $dto->updated_at);

        $array = $dto->toArray();
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('message', $array);
        $this->assertSame('Aprobado.', $array['message']);
    }

    public function testDocumentResponseDtoFromArray(): void
    {
        $data = [
            'id'                => 15,
            'documentable_type' => 'vehicle',
            'documentable_id'   => 8,
            'document_type'     => 'vehicle_registration',
            'status'            => 'PENDING',
            'file_path'         => 'storage/documents/carnet.jpg',
            'extracted_data'    => ['plate' => 'ABC12D'],
            'vehicle_verified'  => true,
            'created_at'        => '2026-10-23 10:00:00',
        ];

        $dto = DocumentResponseDto::fromArray($data);
        $this->assertSame(15, $dto->id);
        $this->assertSame('vehicle', $dto->documentable_type);
        $this->assertSame(8, $dto->documentable_id);
        $this->assertTrue($dto->vehicle_verified);
        $this->assertNull($dto->message);

        $array = $dto->toArray();
        $this->assertArrayNotHasKey('message', $array);
        $this->assertTrue($array['vehicle_verified']);
    }

    public function testKycStatusResponseDto(): void
    {
        $raw = [
            'user_id'              => 5,
            'is_identity_verified' => true,
            'has_verified_vehicle' => false,
            'is_fully_kyc_passed'  => false,
            'documents'            => [
                'identity_card'   => ['status' => 'VERIFIED', 'file_path' => 'https://s3/id.jpg'],
                'passport'        => null,
                'driving_license' => ['status' => 'PENDING', 'file_path' => 'storage/doc.jpg'],
            ],
        ];

        $dto = KycStatusResponseDto::fromArray($raw);

        $this->assertSame(5, $dto->user_id);
        $this->assertTrue($dto->is_identity_verified);
        $this->assertFalse($dto->has_verified_vehicle);
        $this->assertFalse($dto->is_fully_kyc_passed);
        $this->assertNotNull($dto->documents['identity_card']);
        $this->assertNull($dto->documents['passport']);
        $this->assertSame('PENDING', $dto->documents['driving_license']['status']);

        $array = $dto->toArray();
        $this->assertSame(5, $array['user_id']);
        $this->assertCount(3, $array['documents']);
    }

    public function testDocumentS3JobResultDto(): void
    {
        $enqueue = DocumentS3JobResultDto::fromEnqueueResult([
            'enqueued'   => true,
            'job_id'     => 'job-1234',
            'queue'      => 'queue:s3_uploads',
            'target_key' => 'documents/user/1/id.jpg',
        ]);

        $this->assertTrue($enqueue->enqueued);
        $this->assertSame('job-1234', $enqueue->job_id);
        $this->assertSame('queue:s3_uploads', $enqueue->queue);
        $this->assertSame('documents/user/1/id.jpg', $enqueue->target_key);

        $process = DocumentS3JobResultDto::fromProcessResult([
            'processed'   => true,
            'status'      => 'SUCCESS',
            'message'     => 'Subida exitosa',
            'document_id' => 42,
            's3_url'      => 'https://s3.amazonaws.com/bucket/doc.jpg',
        ]);

        $this->assertTrue($process->processed);
        $this->assertSame('SUCCESS', $process->status);
        $this->assertSame(42, $process->document_id);
        $this->assertSame('https://s3.amazonaws.com/bucket/doc.jpg', $process->s3_url);

        $array = $process->toArray();
        $this->assertArrayHasKey('processed', $array);
        $this->assertArrayHasKey('s3_url', $array);
    }
}
