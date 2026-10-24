<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Application\Users\DTOs\SavedAddressResponseDto;
use App\Application\Users\UseCases\{
    CreateSavedAddress,
    DeleteSavedAddress,
    ListSavedAddresses
};
use App\Controllers\SavedAddressController;
use App\Core\{Request, Response};
use App\Domain\Auth\{Entities\Role, ValueObjects\Password};
use App\Domain\Shared\ValueObjects\{Money, Uuid};
use App\Domain\Users\Entities\User;
use App\Domain\Users\ValueObjects\{
    BirthDate,
    Email,
    Names,
    NationalId,
    Phone,
    Surnames
};
use DateTimeImmutable;
use Exception;
use PHPUnit\Framework\TestCase;

final class SavedAddressControllerTest extends TestCase
{
    private CreateSavedAddress $createSavedAddress;
    private ListSavedAddresses $listSavedAddresses;
    private DeleteSavedAddress $deleteSavedAddress;
    private SavedAddressController $controller;

    protected function setUp(): void
    {
        Response::$shouldExit = false;
        $_POST = [];
        $_GET = [];

        $this->createSavedAddress = $this->createStub(CreateSavedAddress::class);
        $this->listSavedAddresses = $this->createStub(ListSavedAddresses::class);
        $this->deleteSavedAddress = $this->createStub(DeleteSavedAddress::class);

        $this->controller = new SavedAddressController(
            createSavedAddress: $this->createSavedAddress,
            listSavedAddresses: $this->listSavedAddresses,
            deleteSavedAddress: $this->deleteSavedAddress
        );
    }

    protected function tearDown(): void
    {
        Response::$shouldExit = true;
        $_POST = [];
        $_GET = [];
    }

    private function createUser(int $id = 1): User
    {
        return new User(
            id: $id,
            uuid: Uuid::generate(),
            names: new Names('Ana'),
            surnames: new Surnames('Rivas'),
            email: new Email('ana@example.com'),
            password: Password::fromPlainText('SecurePass123!'),
            phone: new Phone('+584129876543'),
            walletBalance: new Money(0, 'VES'),
            identificationCard: new NationalId('V-19876543'),
            birthDate: new BirthDate('1992-04-15'),
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
            consentAt: new DateTimeImmutable(),
            deletedAt: null,
            isActive: true,
            roles: [new Role(1, 'client')]
        );
    }

    private function capture(callable $callback): array
    {
        ob_start();
        try {
            $callback();
        } finally {
            $output = (string)ob_get_clean();
        }
        return json_decode($output, true) ?: [];
    }

    public function testIndexUnauthorized(): void
    {
        $res = $this->capture(fn() => $this->controller->index(new Request()));
        $this->assertSame(401, $res['code']);
    }

    public function testIndexSuccess(): void
    {
        $request = new Request();
        $request->setUser($this->createUser());

        $this->listSavedAddresses->method('execute')->willReturn([
            [
                'id'           => 'addr-1',
                'label'        => 'Casa',
                'address_line' => 'Av. Romulo Gallegos',
                'latitude'     => 10.4950,
                'longitude'    => -66.8320,
                'is_favorite'  => true,
            ],
        ]);

        $res = $this->capture(fn() => $this->controller->index($request));
        $this->assertSame(200, $res['code']);
        $this->assertCount(1, $res['data']);
        $this->assertSame('Casa', $res['data'][0]['label']);
    }

    public function testStoreUnauthorized(): void
    {
        $res = $this->capture(fn() => $this->controller->store(new Request()));
        $this->assertSame(401, $res['code']);
    }

    public function testStoreValidationFailure(): void
    {
        $request = new Request();
        $request->setUser($this->createUser());
        $_POST = [];

        $res = $this->capture(fn() => $this->controller->store($request));
        $this->assertSame(422, $res['code']);
        $this->assertArrayHasKey('label', $res['data']);
    }

    public function testStoreSuccess(): void
    {
        $request = new Request();
        $request->setUser($this->createUser());
        $_POST = [
            'label'        => 'Oficina',
            'address_line' => 'Torre Diamante, Piso 5',
            'latitude'     => 10.4850,
            'longitude'    => -66.8650,
            'is_favorite'  => true,
        ];

        $this->createSavedAddress->method('execute')->willReturn([
            'id'           => 'addr-2',
            'label'        => 'Oficina',
            'address_line' => 'Torre Diamante, Piso 5',
            'latitude'     => 10.4850,
            'longitude'    => -66.8650,
            'is_favorite'  => true,
            'created_at'   => '2026-10-23T20:00:00Z',
        ]);

        $res = $this->capture(fn() => $this->controller->store($request));
        $this->assertSame(201, $res['code']);
        $this->assertSame('Oficina', $res['data']['label']);
    }

    public function testDestroyUnauthorized(): void
    {
        $res = $this->capture(fn() => $this->controller->destroy(new Request(), 'addr-1'));
        $this->assertSame(401, $res['code']);
    }

    public function testDestroyNotFound(): void
    {
        $request = new Request();
        $request->setUser($this->createUser());

        $this->deleteSavedAddress->method('execute')
            ->willThrowException(new Exception('Dirección no encontrada.', 404));

        $res = $this->capture(fn() => $this->controller->destroy($request, 'addr-404'));
        $this->assertSame(404, $res['code']);
    }

    public function testDestroySuccess(): void
    {
        $request = new Request();
        $request->setUser($this->createUser());

        $this->deleteSavedAddress->method('execute')->willReturn(true);

        $res = $this->capture(fn() => $this->controller->destroy($request, 'addr-1'));
        $this->assertSame(200, $res['code']);
        $this->assertSame('addr-1', $res['data']['id']);
        $this->assertTrue($res['data']['deleted']);
    }
}
