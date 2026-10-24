<?php

namespace Tests\Unit\Domain\Landing;

use App\Domain\Landing\Entities\HomeGallery;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class HomeGalleryTest extends TestCase
{
    public function testCreateTrimsFieldsAndNullsEmptyOnes(): void
    {
        $item = HomeGallery::create(
            title: '  Hero  ',
            subtitle: ' Sub ',
            description: '',
            buttonText: ' Go ',
            buttonLink: null,
            orderIndex: 4,
            isActive: false,
            imageUrl: 'https://cdn/x.jpg'
        );

        $this->assertNull($item->getId());
        $this->assertSame('Hero', $item->getTitle());
        $this->assertSame('Sub', $item->getSubtitle());
        $this->assertNull($item->getDescription());
        $this->assertSame('Go', $item->getButtonText());
        $this->assertNull($item->getButtonLink());
        $this->assertSame(4, $item->getOrderIndex());
        $this->assertFalse($item->isActive());
        $this->assertSame('https://cdn/x.jpg', $item->getImageUrl());
        $this->assertNull($item->getUpdatedAt());
        $this->assertInstanceOf(DateTimeImmutable::class, $item->getCreatedAt());
    }

    public function testConstructorDefaultsCreatedAt(): void
    {
        $item = new HomeGallery(id: 1, title: 'T');

        $this->assertSame(1, $item->getId());
        $this->assertTrue($item->isActive());
        $this->assertSame(0, $item->getOrderIndex());
        $this->assertInstanceOf(DateTimeImmutable::class, $item->getCreatedAt());
    }

    public function testSettersTrimAndTouchUpdatedAt(): void
    {
        $item = HomeGallery::create(title: 'T');
        $this->assertNull($item->getUpdatedAt());

        $item->setId(10)
            ->setTitle('  New ')
            ->setSubtitle(' s ')
            ->setDescription(' d ')
            ->setButtonText(' b ')
            ->setButtonLink(' /l ')
            ->setOrderIndex(9)
            ->setImageUrl('u');

        $this->assertSame(10, $item->getId());
        $this->assertSame('New', $item->getTitle());
        $this->assertSame('s', $item->getSubtitle());
        $this->assertSame('d', $item->getDescription());
        $this->assertSame('b', $item->getButtonText());
        $this->assertSame('/l', $item->getButtonLink());
        $this->assertSame(9, $item->getOrderIndex());
        $this->assertSame('u', $item->getImageUrl());
        $this->assertInstanceOf(DateTimeImmutable::class, $item->getUpdatedAt());

        $item->setSubtitle('')->setDescription(null)->setButtonText('')->setButtonLink(null)->setImageUrl(null);
        $this->assertNull($item->getSubtitle());
        $this->assertNull($item->getDescription());
        $this->assertNull($item->getButtonText());
        $this->assertNull($item->getButtonLink());
        $this->assertNull($item->getImageUrl());
    }

    public function testActivateAndDeactivate(): void
    {
        $item = HomeGallery::create(title: 'T', isActive: false);

        $this->assertTrue($item->activate()->isActive());
        $this->assertFalse($item->deactivate()->isActive());
        $this->assertNotNull($item->getUpdatedAt());
    }

    public function testToArrayFormatsDatesAndKeys(): void
    {
        $created = new DateTimeImmutable('2026-03-04 05:06:07');
        $updated = new DateTimeImmutable('2026-03-05 05:06:07');
        $item = new HomeGallery(5, 'T', 's', 'd', 'b', '/l', 2, true, 'img', $created, $updated);

        $this->assertSame([
            'id' => 5,
            'title' => 'T',
            'subtitle' => 's',
            'description' => 'd',
            'button_text' => 'b',
            'button_link' => '/l',
            'order_index' => 2,
            'is_active' => true,
            'image_url' => 'img',
            'created_at' => $created->format('c'),
            'updated_at' => $updated->format('c'),
        ], $item->toArray());

        $this->assertNull(HomeGallery::create(title: 'x')->toArray()['updated_at']);
    }
}
