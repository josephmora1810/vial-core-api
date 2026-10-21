<?php

namespace Tests\Unit\Application;

use App\Application\Auth\UseCases\GetKycStatus;
use App\Domain\Auth\Entities\Document;
use App\Domain\Users\Entities\User;
use App\Domain\Auth\Enums\DocumentStatus;
use App\Domain\Auth\Enums\DocumentType;
use App\Domain\Users\ValueObjects\BirthDate;
use App\Domain\Users\ValueObjects\Email;
use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Users\ValueObjects\Names;
use App\Domain\Users\ValueObjects\NationalId;
use App\Domain\Auth\ValueObjects\Password;
use App\Domain\Users\ValueObjects\Phone;
use App\Domain\Users\ValueObjects\Surnames;
use App\Domain\Shared\ValueObjects\Uuid;
use PHPUnit\Framework\TestCase;
use Tests\Doubles\InMemoryDocumentRepository;
use Tests\Doubles\InMemoryUserRepository;

class GetKycStatusTest extends TestCase
{
    private InMemoryDocumentRepository $docRepo;
    private InMemoryUserRepository $userRepo;
    private GetKycStatus $useCase;

    protected function setUp(): void
    {
        $this->docRepo = new InMemoryDocumentRepository();
        $this->userRepo = new InMemoryUserRepository();
        $this->useCase = new GetKycStatus($this->docRepo, $this->userRepo);

        $user = new User(
            id: 1,
            uuid: Uuid::generate(),
            names: new Names('Ana'),
            surnames: new Surnames('Gomez'),
            email: new Email('ana@example.com'),
            password: Password::fromPlainText('Pass123!'),
            phone: new Phone('+584145556677'),
            walletBalance: new Money(0.00, 'VES'),
            identificationCard: new NationalId('V-24555666'),
            birthDate: new BirthDate('1998-11-25')
        );
        $this->userRepo->save($user);
    }

    public function test_returns_pending_status_when_documents_not_verified(): void
    {
        $doc = Document::create('user', 1, DocumentType::IdentityCard, '/path/ci.jpg', null, DocumentStatus::Pending);
        $this->docRepo->save($doc);

        $status = $this->useCase->execute(1);

        $this->assertEquals(1, $status['user_id']);
        $this->assertFalse($status['is_identity_verified']);
        $this->assertFalse($status['has_verified_vehicle']);
        $this->assertFalse($status['is_fully_kyc_passed']);
        $this->assertEquals(DocumentStatus::Pending->getValue(), $status['documents']['identity_card']['status']);
    }

    public function test_returns_fully_verified_when_both_identity_and_vehicle_verified(): void
    {
        $idDoc = Document::create('user', 1, DocumentType::IdentityCard, '/path/ci.jpg', null, DocumentStatus::Verified);
        $this->docRepo->save($idDoc);

        $vehDoc = Document::create('vehicle', 10, DocumentType::VehicleRegistration, '/path/carnet.jpg', null, DocumentStatus::Verified);
        $this->docRepo->save($vehDoc);

        $status = $this->useCase->execute(1);

        $this->assertTrue($status['is_identity_verified']);
        $this->assertTrue($status['has_verified_vehicle']);
        $this->assertTrue($status['is_fully_kyc_passed']);
    }
}
