<?php

namespace Tests\Unit\Domain\Entities;

use App\Domain\Auth\Entities\Document;
use App\Domain\Auth\Enums\{DocumentStatus, DocumentType};
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class DocumentTest extends TestCase
{
    public function testCanCreateDocumentViaStaticFactory(): void
    {
        $doc = Document::create(
            documentableType: 'user',
            documentableId: 42,
            documentType: DocumentType::IdentityCard,
            filePath: '/storage/documents/id_42.jpg',
            extractedData: ['national_id' => 'V-12345678'],
            status: DocumentStatus::Pending
        );

        $this->assertNull($doc->getId());
        $this->assertSame('user', $doc->getDocumentableType());
        $this->assertSame(42, $doc->getDocumentableId());
        $this->assertSame(DocumentType::IdentityCard, $doc->getDocumentType());
        $this->assertSame('/storage/documents/id_42.jpg', $doc->getFilePath());
        $this->assertSame(['national_id' => 'V-12345678'], $doc->getExtractedData());
        $this->assertSame(DocumentStatus::Pending, $doc->getStatus());
        $this->assertTrue($doc->isPending());
        $this->assertFalse($doc->isVerified());
        $this->assertFalse($doc->isRejected());
        $this->assertInstanceOf(DateTimeImmutable::class, $doc->getCreatedAt());
        $this->assertNull($doc->getUpdatedAt());
    }

    public function testSettersAndUpdateTouch(): void
    {
        $doc = new Document(
            id: 1,
            documentableType: 'vehicle',
            documentableId: 99,
            documentType: DocumentType::VehicleRegistration,
            filePath: '/old/path.jpg',
            extractedData: null,
            status: DocumentStatus::Pending
        );

        $doc->setId(10);
        $this->assertSame(10, $doc->getId());

        $doc->setFilePath('/new/path.jpg');
        $this->assertSame('/new/path.jpg', $doc->getFilePath());
        $this->assertNotNull($doc->getUpdatedAt());

        $doc->setExtractedData(['plate_number' => 'ABC123D']);
        $this->assertSame(['plate_number' => 'ABC123D'], $doc->getExtractedData());
    }

    public function testVerifyAndRejectTransitions(): void
    {
        $doc = Document::create(
            documentableType: 'user',
            documentableId: 5,
            documentType: DocumentType::DrivingLicense,
            filePath: '/storage/license.jpg'
        );

        $this->assertTrue($doc->isPending());

        $doc->verify();
        $this->assertTrue($doc->isVerified());
        $this->assertFalse($doc->isPending());
        $this->assertFalse($doc->isRejected());
        $this->assertSame(DocumentStatus::Verified, $doc->getStatus());
        $this->assertNotNull($doc->getUpdatedAt());

        $doc->reject();
        $this->assertTrue($doc->isRejected());
        $this->assertFalse($doc->isVerified());
        $this->assertFalse($doc->isPending());
        $this->assertSame(DocumentStatus::Rejected, $doc->getStatus());
    }

    public function testResubmitDocument(): void
    {
        $doc = Document::create(
            documentableType: 'user',
            documentableId: 5,
            documentType: DocumentType::IdentityCard,
            filePath: '/storage/bad_scan.jpg',
            status: DocumentStatus::Rejected
        );

        $this->assertTrue($doc->isRejected());

        $doc->resubmit('/storage/good_scan.jpg', ['confidence' => 0.99]);

        $this->assertSame('/storage/good_scan.jpg', $doc->getFilePath());
        $this->assertSame(['confidence' => 0.99], $doc->getExtractedData());
        $this->assertTrue($doc->isPending());
        $this->assertNotNull($doc->getUpdatedAt());
    }
}
