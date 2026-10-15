<?php

namespace Tests\Unit\Application;

use App\Application\UseCases\CreateSavedAddress;
use App\Domain\Entities\User;
use App\Domain\ValueObjects\BirthDate;
use App\Domain\ValueObjects\Email;
use App\Domain\ValueObjects\Money;
use App\Domain\ValueObjects\Names;
use App\Domain\ValueObjects\NationalId;
use App\Domain\ValueObjects\Password;
use App\Domain\ValueObjects\Phone;
use App\Domain\ValueObjects\Surnames;
use App\Domain\ValueObjects\Uuid;
use Exception;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tests\Doubles\InMemorySavedAddressRepository;
use Tests\Doubles\InMemoryUserRepository;

class CreateSavedAddressTest extends TestCase
{
    private InMemorySavedAddressRepository $addressRepo;
    private InMemoryUserRepository $userRepo;
    private CreateSavedAddress $useCase;

    protected function setUp(): void
    {
        $this->addressRepo = new InMemorySavedAddressRepository();
        $this->userRepo = new InMemoryUserRepository();
        $this->useCase = new CreateSavedAddress($this->addressRepo, $this->userRepo);

        $user = new User(
            id: 1,
            uuid: Uuid::generate(),
            names: new Names('Luis'),
            surnames: new Surnames('Alvarez'),
            email: new Email('luis@example.com'),
            password: Password::fromPlainText('ClaveValida1!'),
            phone: new Phone('+584123334455'),
            walletBalance: new Money(0.00, 'VES'),
            identificationCard: new NationalId('V-19888777'),
            birthDate: new BirthDate('1994-06-20')
        );
        $this->userRepo->save($user);
    }

    public function test_creates_saved_address_successfully(): void
    {
        $result = $this->useCase->execute(
            userId: 1,
            label: 'Mi Casa',
            addressLine: 'Av. Libertador, Edif. Centro, Caracas',
            latitude: 10.490000,
            longitude: -66.850000,
            isFavorite: true
        );

        $this->assertNotEmpty($result['id']); // UUID v7 string
        $this->assertEquals('Mi Casa', $result['label']);
        $this->assertEquals('Av. Libertador, Edif. Centro, Caracas', $result['address_line']);
        $this->assertEquals(10.490000, $result['latitude']);
        $this->assertEquals(-66.850000, $result['longitude']);
        $this->assertTrue($result['is_favorite']);
    }

    public function test_throws_exception_when_coordinates_are_out_of_bounds(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // Latitud inválida (95 > 90)
        $this->useCase->execute(
            userId: 1,
            label: 'Lugar Imposible',
            addressLine: 'Coordenada Falsa',
            latitude: 95.0,
            longitude: -66.0
        );
    }

    public function test_throws_exception_when_user_not_found(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionCode(404);

        $this->useCase->execute(
            userId: 999,
            label: 'Casa',
            addressLine: 'Alguna calle',
            latitude: 10.0,
            longitude: -66.0
        );
    }
}
