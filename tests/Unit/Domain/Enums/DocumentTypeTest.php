<?php

namespace Tests\Unit\Domain\Enums;

use App\Domain\Auth\Enums\DocumentType;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DocumentTypeTest extends TestCase
{
    public function testCanInstantiateFromStringWithExactAndLooseCasing(): void
    {
        $this->assertSame(DocumentType::IdentityCard, DocumentType::fromString('identity_card'));
        $this->assertSame(DocumentType::IdentityCard, DocumentType::fromString('IDENTITY_CARD '));
        $this->assertSame(DocumentType::Passport, DocumentType::fromString('passport'));
        $this->assertSame(DocumentType::VehicleRegistration, DocumentType::fromString('vehicle_registration'));
        $this->assertSame(DocumentType::NivCertificate, DocumentType::fromString('niv_certificate'));
        $this->assertSame(DocumentType::DrivingLicense, DocumentType::fromString('driving_license'));
    }

    public function testThrowsExceptionOnInvalidDocumentType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El tipo de documento "invalid_doc" no es válido.');

        DocumentType::fromString('invalid_doc');
    }

    public function testValuesAndGetValue(): void
    {
        $values = DocumentType::values();
        $this->assertContains('identity_card', $values);
        $this->assertContains('passport', $values);
        $this->assertContains('vehicle_registration', $values);
        $this->assertContains('niv_certificate', $values);
        $this->assertContains('driving_license', $values);

        $this->assertSame('identity_card', DocumentType::IdentityCard->getValue());
        $this->assertSame('passport', DocumentType::Passport->getValue());
    }
}
