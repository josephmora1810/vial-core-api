<?php

declare(strict_types=1);

namespace Tests\Unit\Application\DTOs;

use App\Application\Users\DTOs\{
    SavedAddressDeletedResponseDto,
    SavedAddressResponseDto,
    UserAvatarResponseDto,
    UserModerationResponseDto,
    UserResponseDto
};
use App\Domain\Auth\Entities\Role;
use App\Domain\Auth\ValueObjects\Password;
use App\Domain\Shared\ValueObjects\{Coordinates, Money, Uuid};
use App\Domain\Users\Entities\{SavedAddress, User};
use App\Domain\Users\ValueObjects\{
    BirthDate,
    Email,
    Names,
    NationalId,
    Phone,
    Surnames
};
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class UserDtosTest extends TestCase
{
    public function testSavedAddressResponseDto(): void
    {
        $address = SavedAddress::create(
            userId: 1,
            label: 'Casa',
            addressLine: 'Av. Francisco de Miranda, Edif. A, Apt 1',
            coordinates: new Coordinates(10.4880, -66.8791),
            isFavorite: true
        );

        $dto = SavedAddressResponseDto::fromEntity($address);

        $this->assertSame('Casa', $dto->label);
        $this->assertSame('Av. Francisco de Miranda, Edif. A, Apt 1', $dto->address_line);
        $this->assertSame(10.4880, $dto->latitude);
        $this->assertSame(-66.8791, $dto->longitude);
        $this->assertTrue($dto->is_favorite);
        $this->assertArrayHasKey('id', $dto->toArray());
    }

    public function testSavedAddressDeletedResponseDto(): void
    {
        $dto = new SavedAddressDeletedResponseDto(id: 'addr-uuid-123', deleted: true);

        $this->assertSame('addr-uuid-123', $dto->id);
        $this->assertTrue($dto->deleted);
        $this->assertSame(['id' => 'addr-uuid-123', 'deleted' => true], $dto->toArray());
    }

    public function testUserModerationResponseDto(): void
    {
        $dto = UserModerationResponseDto::fromArray([
            'user_id'       => 10,
            'status'        => 'suspended',
            'reason'        => 'Violación de términos de servicio',
            'admin_user_id' => 1,
        ]);

        $this->assertSame(10, $dto->user_id);
        $this->assertSame('suspended', $dto->status);
        $this->assertSame('Violación de términos de servicio', $dto->reason);
        $this->assertSame(1, $dto->admin_user_id);
        $this->assertSame([
            'user_id'       => 10,
            'status'        => 'suspended',
            'reason'        => 'Violación de términos de servicio',
            'admin_user_id' => 1,
        ], $dto->toArray());
    }

    public function testUserAvatarResponseDto(): void
    {
        $dto = UserAvatarResponseDto::fromArray([
            'url'      => 'https://s3.amazonaws.com/bucket/avatar.webp',
            'initials' => 'JP',
            'image_id' => 42,
        ]);

        $this->assertSame('https://s3.amazonaws.com/bucket/avatar.webp', $dto->url);
        $this->assertSame('JP', $dto->initials);
        $this->assertSame(42, $dto->image_id);
        $this->assertSame([
            'url'      => 'https://s3.amazonaws.com/bucket/avatar.webp',
            'initials' => 'JP',
            'image_id' => 42,
        ], $dto->toArray());
    }

    public function testUserResponseDto(): void
    {
        $user = new User(
            id: 7,
            uuid: Uuid::generate(),
            names: new Names('Maria'),
            surnames: new Surnames('Gomez'),
            email: new Email('maria@example.com'),
            password: Password::fromPlainText('SecurePass123!'),
            phone: new Phone('+584141112233'),
            walletBalance: new Money(50.0, 'VES'),
            identificationCard: new NationalId('V-20123456'),
            birthDate: new BirthDate('1995-08-20'),
            createdAt: new DateTimeImmutable('2026-01-01 10:00:00'),
            updatedAt: null,
            consentAt: new DateTimeImmutable('2026-01-01 10:00:00'),
            deletedAt: null,
            isActive: true,
            roles: [new Role(1, 'client')]
        );

        $dto = UserResponseDto::fromEntity($user, 'https://example.com/avatar.png');

        $this->assertSame('Maria', $dto->names);
        $this->assertSame('Gomez', $dto->surnames);
        $this->assertSame('Maria Gomez', $dto->fullName);
        $this->assertSame('MG', $dto->initials);
        $this->assertSame('https://example.com/avatar.png', $dto->avatarUrl);
        $this->assertSame(50.0, $dto->walletBalance);
        $this->assertTrue($dto->isActive);
        $this->assertSame('client', $dto->role);
        $this->assertArrayHasKey('uuid', $dto->toArray());
    }
}
