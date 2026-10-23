<?php

namespace Tests\Doubles;

use App\Domain\Towing\Entities\Rating;
use App\Domain\Towing\Repositories\RatingRepositoryInterface;

class InMemoryRatingRepository implements RatingRepositoryInterface
{
    /** @var array<int, Rating> */
    private array $items = [];
    private int $nextId = 1;

    public function save(Rating $rating): void
    {
        $rating->setId($this->nextId);
        $this->items[$this->nextId] = $rating;
        $this->nextId++;
    }

    public function findByServiceAndUser(int $towServiceId, int $userId): ?Rating
    {
        foreach ($this->items as $r) {
            if ($r->getTowServiceId() === $towServiceId && $r->getUserId() === $userId) {
                return $r;
            }
        }
        return null;
    }

    public function findByRatedUserId(int $ratedUserId): array
    {
        return array_values(array_filter(
            $this->items,
            fn(Rating $r) => $r->getRatedUserId() === $ratedUserId
        ));
    }

    public function getAverageRatingForUser(int $ratedUserId): float
    {
        $ratings = $this->findByRatedUserId($ratedUserId);
        if (empty($ratings)) {
            return 5.0;
        }

        $sum = array_reduce($ratings, fn(int $carry, Rating $r) => $carry + $r->getRating(), 0);
        return round($sum / count($ratings), 2);
    }
}
