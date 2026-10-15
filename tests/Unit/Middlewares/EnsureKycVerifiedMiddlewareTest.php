<?php

namespace Tests\Unit\Middlewares;

use App\Core\Request;
use App\Domain\Entities\Document;
use App\Domain\Entities\User;
use App\Domain\Enums\DocumentStatus;
use App\Domain\Enums\DocumentType;
use App\Domain\ValueObjects\BirthDate;
use App\Domain\ValueObjects\Email;
use App\Domain\ValueObjects\Money;
use App\Domain\ValueObjects\Names;
use App\Domain\ValueObjects\NationalId;
use App\Domain\ValueObjects\Password;
use App\Domain\ValueObjects\Phone;
use App\Domain\ValueObjects\Surnames;
use App\Domain\ValueObjects\Uuid;
use App\Middlewares\EnsureKycVerifiedMiddleware;
use Exception;
use PHPUnit\Framework\TestCase;
use Tests\Doubles\InMemoryDocumentRepository;

class EnsureKycVerifiedMiddlewareTest extends TestCase
{
    private InMemoryDocumentRepository $docRepo;
    private User $user;

    protected function setUp(): void
    {
        $this->docRepo = new InMemoryDocumentRepository();
        $this->user = new User(
            id: 1,
            uuid: Uuid::generate(),
            names: new Names('Juan Carlos'),
            surnames: new Surnames('Perez Gonzalez'),
            email: new Email('juan@example.com'),
            password: Password::fromPlainText('ClaveFuerte123!'),
            phone: new Phone('+584121234567'),
            walletBalance: new Money(0.00, 'VES'),
            identificationCard: new NationalId('V-12345678'),
            birthDate: new BirthDate('1990-05-15')
        );
    }

    public function test_throws_401_when_user_not_authenticated(): void
    {
        $middleware = new EnsureKycVerifiedMiddleware($this->docRepo, 'full');
        $request = new Request([], [], [], [], [], []);

        $this->expectException(Exception::class);
        $this->expectExceptionCode(401);

        $middleware->handle($request, fn() => true);
    }

    public function test_throws_403_when_identity_is_pending(): void
    {
        $middleware = new EnsureKycVerifiedMiddleware($this->docRepo, 'identity');
        $request = new Request([], [], [], [], [], []);
        $request->setUser($this->user);

        // Subir cédula en estado PENDING
        $doc = Document::create('user', 1, DocumentType::IdentityCard, '/path/ci.jpg', null, DocumentStatus::Pending);
        $this->docRepo->save($doc);

        $this->expectException(Exception::class);
        $this->expectExceptionCode(403);
        $this->expectExceptionMessage('Tu identidad se encuentra en proceso de validación');

        $middleware->handle($request, fn() => true);
    }

    public function test_throws_403_when_identity_is_rejected(): void
    {
        $middleware = new EnsureKycVerifiedMiddleware($this->docRepo, 'identity');
        $request = new Request([], [], [], [], [], []);
        $request->setUser($this->user);

        // Subir cédula en estado REJECTED
        $doc = Document::create('user', 1, DocumentType::IdentityCard, '/path/ci.jpg', null, DocumentStatus::Rejected);
        $this->docRepo->save($doc);

        $this->expectException(Exception::class);
        $this->expectExceptionCode(403);
        $this->expectExceptionMessage('Tu documento de identidad fue rechazado');

        $middleware->handle($request, fn() => true);
    }

    public function test_passes_when_identity_verified_in_identity_mode(): void
    {
        $middleware = new EnsureKycVerifiedMiddleware($this->docRepo, 'identity');
        $request = new Request([], [], [], [], [], []);
        $request->setUser($this->user);

        $doc = Document::create('user', 1, DocumentType::IdentityCard, '/path/ci.jpg', null, DocumentStatus::Verified);
        $this->docRepo->save($doc);

        $executedNext = false;
        $middleware->handle($request, function () use (&$executedNext) {
            $executedNext = true;
            return true;
        });

        $this->assertTrue($executedNext);
    }

    public function test_throws_403_when_identity_verified_but_vehicle_doc_missing_in_full_mode(): void
    {
        $middleware = new EnsureKycVerifiedMiddleware($this->docRepo, 'full');
        $request = new Request([], [], [], [], [], []);
        $request->setUser($this->user);

        // Identidad aprobada, pero sin vehículo
        $doc = Document::create('user', 1, DocumentType::IdentityCard, '/path/ci.jpg', null, DocumentStatus::Verified);
        $this->docRepo->save($doc);

        $this->expectException(Exception::class);
        $this->expectExceptionCode(403);
        $this->expectExceptionMessage('Debes tener registrado y verificado el carnet de circulación o N.I.V de tu vehículo');

        $middleware->handle($request, fn() => true);
    }

    public function test_passes_when_both_identity_and_vehicle_verified_in_full_mode(): void
    {
        $middleware = new EnsureKycVerifiedMiddleware($this->docRepo, 'full');
        $request = new Request([], [], [], [], [], []);
        $request->setUser($this->user);

        // Cédula verificada
        $idDoc = Document::create('user', 1, DocumentType::IdentityCard, '/path/ci.jpg', null, DocumentStatus::Verified);
        $this->docRepo->save($idDoc);

        // Carnet de circulación verificado
        $vehDoc = Document::create('vehicle', 10, DocumentType::VehicleRegistration, '/path/carnet.jpg', null, DocumentStatus::Verified);
        $this->docRepo->save($vehDoc);

        $executedNext = false;
        $middleware->handle($request, function () use (&$executedNext) {
            $executedNext = true;
            return true;
        });

        $this->assertTrue($executedNext);
    }
}
