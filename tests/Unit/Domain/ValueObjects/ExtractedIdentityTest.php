<?php

namespace Tests\Unit\Domain\ValueObjects;

use App\Domain\ValueObjects\BirthDate;
use App\Domain\ValueObjects\ExtractedIdentity;
use App\Domain\ValueObjects\Names;
use App\Domain\ValueObjects\NationalId;
use App\Domain\ValueObjects\Surnames;
use PHPUnit\Framework\TestCase;

class ExtractedIdentityTest extends TestCase
{
    public function test_can_create_and_read_extracted_identity(): void
    {
        $id = new NationalId('V80123456');
        $names = new Names('ANDREÍNA SOFÍA');
        $surnames = new Surnames('QUINTERO BASTIDAS');
        $dob = new BirthDate('1990-04-17');

        $identity = new ExtractedIdentity(
            nationalId: $id,
            names: $names,
            surnames: $surnames,
            birthDate: $dob,
            confidenceScore: 0.99,
            status: 'success',
            rawPayload: ['raw' => true]
        );

        $this->assertTrue($id->equals($identity->getNationalId()));
        $this->assertTrue($names->equals($identity->getNames()));
        $this->assertTrue($surnames->equals($identity->getSurnames()));
        $this->assertTrue($dob->equals($identity->getBirthDate()));
        $this->assertSame(0.99, $identity->getConfidenceScore());
        $this->assertSame('success', $identity->getStatus());
        $this->assertSame(['raw' => true], $identity->getRawPayload());
    }
}
