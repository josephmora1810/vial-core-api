<?php

namespace Tests\Unit\Application\DTOs;

use App\Application\Landing\DTOs\{HomeGalleryDeletedResponseDto, HomeGalleryResponseDto};
use App\Domain\Landing\Entities\HomeGallery;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class LandingDtosTest extends TestCase
{
    public function testHomeGalleryResponseDtoFromEntityMatchesEntityToArray(): void
    {
        $item = new HomeGallery(
            id: 7,
            title: 'Hero',
            subtitle: 'Sub',
            description: 'Desc',
            buttonText: 'Go',
            buttonLink: '/planes',
            orderIndex: 3,
            isActive: false,
            imageUrl: 'https://cdn.test/a.jpg',
            createdAt: new DateTimeImmutable('2026-01-01 10:00:00'),
            updatedAt: new DateTimeImmutable('2026-01-02 11:00:00')
        );

        $dto = HomeGalleryResponseDto::fromEntity($item);

        $this->assertSame(7, $dto->id);
        $this->assertSame('Hero', $dto->title);
        $this->assertFalse($dto->is_active);
        $this->assertSame(3, $dto->order_index);
        $this->assertSame($item->toArray(), $dto->toArray());
    }

    public function testHomeGalleryResponseDtoFromArrayAppliesDefaultsAndCasts(): void
    {
        $dto = HomeGalleryResponseDto::fromArray(['id' => '5', 'title' => 'T', 'order_index' => '2', 'is_active' => 1]);

        $this->assertSame(5, $dto->id);
        $this->assertSame(2, $dto->order_index);
        $this->assertTrue($dto->is_active);
        $this->assertNull($dto->subtitle);
        $this->assertNull($dto->description);
        $this->assertNull($dto->button_text);
        $this->assertNull($dto->button_link);
        $this->assertNull($dto->image_url);
        $this->assertNull($dto->created_at);
        $this->assertNull($dto->updated_at);

        $empty = HomeGalleryResponseDto::fromArray([]);
        $this->assertNull($empty->id);
        $this->assertSame('', $empty->title);
        $this->assertSame(0, $empty->order_index);
        $this->assertTrue($empty->is_active);
    }

    public function testHomeGalleryResponseDtoToArrayHasExactKeys(): void
    {
        $keys = array_keys(HomeGalleryResponseDto::fromArray(['title' => 'x'])->toArray());

        $this->assertSame([
            'id', 'title', 'subtitle', 'description', 'button_text', 'button_link',
            'order_index', 'is_active', 'image_url', 'created_at', 'updated_at',
        ], $keys);
    }

    public function testHomeGalleryResponseDtoCollectionToArray(): void
    {
        $a = HomeGallery::create(title: 'A');
        $b = HomeGallery::create(title: 'B');

        $list = HomeGalleryResponseDto::collectionToArray([3 => $a, 5 => $b]);

        $this->assertCount(2, $list);
        $this->assertSame([0, 1], array_keys($list));
        $this->assertSame('A', $list[0]['title']);
        $this->assertSame('B', $list[1]['title']);
        $this->assertSame([], HomeGalleryResponseDto::collectionToArray([]));
    }

    public function testHomeGalleryDeletedResponseDto(): void
    {
        $dto = new HomeGalleryDeletedResponseDto(true, 9);
        $this->assertSame(['deleted' => true, 'id' => 9], $dto->toArray());

        $fromArray = HomeGalleryDeletedResponseDto::fromArray(['deleted' => 1, 'id' => '12']);
        $this->assertTrue($fromArray->deleted);
        $this->assertSame(12, $fromArray->id);

        $defaults = HomeGalleryDeletedResponseDto::fromArray([]);
        $this->assertSame(['deleted' => false, 'id' => 0], $defaults->toArray());
    }
}
