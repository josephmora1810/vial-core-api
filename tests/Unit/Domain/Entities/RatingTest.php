<?php

namespace Tests\Unit\Domain\Entities;

use App\Domain\Towing\Entities\Rating;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class RatingTest extends TestCase
{
    public function testCanCreateValidRating(): void
    {
        $rating = Rating::create(
            towServiceId: 10,
            userId: 5,
            ratedUserId: 99,
            rating: 5,
            comment: 'Excelente servicio, muy puntual y profesional'
        );

        $this->assertNull($rating->getId());
        $this->assertSame(10, $rating->getTowServiceId());
        $this->assertSame(5, $rating->getUserId());
        $this->assertSame(99, $rating->getRatedUserId());
        $this->assertSame(5, $rating->getRating());
        $this->assertSame('Excelente servicio, muy puntual y profesional', $rating->getComment());
        $this->assertNotNull($rating->getCreatedAt());
    }

    public function testRejectsRatingBelowOne(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Rating::create(
            towServiceId: 10,
            userId: 5,
            ratedUserId: 99,
            rating: 0
        );
    }

    public function testRejectsRatingAboveFive(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Rating::create(
            towServiceId: 10,
            userId: 5,
            ratedUserId: 99,
            rating: 6
        );
    }

    public function testDirectInstantiationAlsoValidatesRatingInvariant(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Rating(
            id: 1,
            towServiceId: 10,
            userId: 5,
            ratedUserId: 99,
            rating: -1,
            comment: null,
            createdAt: null
        );
    }
}
