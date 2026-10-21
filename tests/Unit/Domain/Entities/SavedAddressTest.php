<?php

namespace Tests\Unit\Domain\Entities;

use App\Domain\Users\Entities\SavedAddress;
use App\Domain\Shared\ValueObjects\Coordinates;
use App\Domain\Shared\ValueObjects\Uuid;
use PHPUnit\Framework\TestCase;

class SavedAddressTest extends TestCase
{
    public function test_creates_saved_address_with_uuid_and_coordinates(): void
    {
        $coords = new Coordinates(10.488012, -66.862145);
        $address = SavedAddress::create(
            userId: 1,
            label: 'Mi Casa',
            addressLine: 'Calle Los Pinos, Qta María',
            coordinates: $coords,
            isFavorite: true
        );

        $this->assertInstanceOf(Uuid::class, $address->getUuid());
        $this->assertEquals(1, $address->getUserId());
        $this->assertEquals('Mi Casa', $address->getLabel());
        $this->assertEquals('Calle Los Pinos, Qta María', $address->getAddressLine());
        $this->assertTrue($address->isFavorite());
        $this->assertEquals(10.488012, $address->getCoordinates()->getLatitude());
        $this->assertEquals(-66.862145, $address->getCoordinates()->getLongitude());
    }

    public function test_marks_and_unmarks_favorite(): void
    {
        $address = SavedAddress::create(
            userId: 1,
            label: 'Taller',
            addressLine: 'Zona Industrial Boleita',
            coordinates: new Coordinates(10.500000, -66.800000)
        );

        $this->assertFalse($address->isFavorite());

        $address->markAsFavorite();
        $this->assertTrue($address->isFavorite());

        $address->unmarkFavorite();
        $this->assertFalse($address->isFavorite());
    }
}
