<?php

namespace Tests\Unit\Application;

use App\Application\Auth\UseCases\ReviewKycDocument;
use App\Domain\Auth\Entities\Document;
use App\Domain\Auth\Enums\{DocumentStatus, DocumentType};
use App\Domain\Shared\ValueObjects\Uuid;
use App\Domain\Vehicles\Entities\Vehicle;
use App\Domain\Vehicles\Enums\VehicleStatus;
use App\Domain\Vehicles\ValueObjects\{EngineSerial, Niv, PlateNumber};
use Exception;
use PHPUnit\Framework\TestCase;
use Tests\Doubles\{InMemoryDocumentRepository, InMemoryVehicleRepository};

class ReviewKycDocumentTest extends TestCase
{
    private InMemoryDocumentRepository $docRepo;
    private InMemoryVehicleRepository $vehicleRepo;
    private ReviewKycDocument $useCase;

    protected function setUp(): void
    {
        $this->docRepo = new InMemoryDocumentRepository();
        $this->vehicleRepo = new InMemoryVehicleRepository();
        $this->useCase = new ReviewKycDocument($this->docRepo, $this->vehicleRepo);
    }

    public function testListByStatusReturnsPendingDocumentsByDefault(): void
    {
        $doc1 = Document::create(
            documentableType: 'user',
            documentableId: 10,
            documentType: DocumentType::IdentityCard,
            filePath: '/tmp/id1.jpg',
            status: DocumentStatus::Pending
        );
        $this->docRepo->save($doc1);

        $doc2 = Document::create(
            documentableType: 'user',
            documentableId: 11,
            documentType: DocumentType::IdentityCard,
            filePath: '/tmp/id2.jpg',
            status: DocumentStatus::Verified
        );
        $this->docRepo->save($doc2);

        $pending = $this->useCase->listByStatus();
        $this->assertCount(1, $pending);
        $this->assertSame($doc1->getId(), $pending[0]['id']);
        $this->assertSame('PENDING', $pending[0]['status']);
    }

    public function testThrowsExceptionWhenDocumentNotFound(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionCode(404);
        $this->expectExceptionMessage('El documento solicitado no existe.');

        $this->useCase->execute(999, 'VERIFIED');
    }

    public function testThrowsExceptionWhenInvalidDecision(): void
    {
        $doc = Document::create(
            documentableType: 'user',
            documentableId: 10,
            documentType: DocumentType::IdentityCard,
            filePath: '/tmp/id1.jpg'
        );
        $this->docRepo->save($doc);

        $this->expectException(Exception::class);
        $this->expectExceptionCode(422);
        $this->expectExceptionMessage('El estado de revisión debe ser VERIFIED (aprobar) o REJECTED (rechazar).');

        $this->useCase->execute((int)$doc->getId(), 'MAYBE');
    }

    public function testApprovesUserDocument(): void
    {
        $doc = Document::create(
            documentableType: 'user',
            documentableId: 10,
            documentType: DocumentType::IdentityCard,
            filePath: '/tmp/id1.jpg'
        );
        $this->docRepo->save($doc);

        $result = $this->useCase->execute(
            documentId: (int)$doc->getId(),
            rawDecision: 'VERIFIED',
            reason: 'Documento nítido y legible',
            adminUserId: 1
        );

        $this->assertSame('VERIFIED', $result['status']);
        $this->assertNull($result['vehicle_verified']);
        $this->assertSame(1, $result['extracted_data']['reviewed_by_admin_id']);
        $this->assertSame('Documento nítido y legible', $result['extracted_data']['review_reason']);

        $savedDoc = $this->docRepo->findById((int)$doc->getId());
        $this->assertTrue($savedDoc->isVerified());
    }

    public function testApprovesVehicleDocumentAndActivatesVehicle(): void
    {
        $vehicle = new Vehicle(
            id: null,
            uuid: Uuid::generate(),
            userId: 5,
            modelId: 1,
            plateNumber: new PlateNumber('AB123CD'),
            niv: new Niv('1HGBH41JXMN109186'),
            engineSerial: new EngineSerial('ENG-9876543210'),
            year: 2021,
            color: 'Azul',
            status: VehicleStatus::Pending,
            isVerified: false
        );
        $this->vehicleRepo->save($vehicle);

        $doc = Document::create(
            documentableType: 'vehicle',
            documentableId: (int)$vehicle->getId(),
            documentType: DocumentType::VehicleRegistration,
            filePath: '/tmp/carnet.jpg'
        );
        $this->docRepo->save($doc);

        $result = $this->useCase->execute(
            documentId: (int)$doc->getId(),
            rawDecision: 'APPROVED',
            adminUserId: 2
        );

        $this->assertSame('VERIFIED', $result['status']);
        $this->assertTrue($result['vehicle_verified']);

        $updatedVehicle = $this->vehicleRepo->findById((int)$vehicle->getId());
        $this->assertTrue($updatedVehicle->isVerified());
        $this->assertSame(VehicleStatus::Active, $updatedVehicle->getStatus());
    }

    public function testRejectsVehicleDocumentAndUnverifiesVehicle(): void
    {
        $vehicle = new Vehicle(
            id: null,
            uuid: Uuid::generate(),
            userId: 5,
            modelId: 1,
            plateNumber: new PlateNumber('XY987AB'),
            niv: new Niv('2HGBH41JXMN109187'),
            engineSerial: new EngineSerial('ENG-1111111111'),
            year: 2020,
            color: 'Rojo',
            status: VehicleStatus::Active,
            isVerified: true
        );
        $this->vehicleRepo->save($vehicle);

        $doc = Document::create(
            documentableType: 'vehicle',
            documentableId: (int)$vehicle->getId(),
            documentType: DocumentType::VehicleRegistration,
            filePath: '/tmp/carnet_borroso.jpg',
            status: DocumentStatus::Pending
        );
        $this->docRepo->save($doc);

        $result = $this->useCase->execute(
            documentId: (int)$doc->getId(),
            rawDecision: 'REJECTED',
            reason: 'El carnet está cortado y no se lee el NIV',
            adminUserId: 2
        );

        $this->assertSame('REJECTED', $result['status']);
        $this->assertFalse($result['vehicle_verified']);
        $this->assertSame('El carnet está cortado y no se lee el NIV', $result['extracted_data']['review_reason']);

        $updatedVehicle = $this->vehicleRepo->findById((int)$vehicle->getId());
        $this->assertFalse($updatedVehicle->isVerified());
        $this->assertSame(VehicleStatus::Pending, $updatedVehicle->getStatus());
    }
}
