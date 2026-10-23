<?php

namespace App\Domain\Towing\Entities;

use DateTimeImmutable;
use InvalidArgumentException;

class Rating
{
    public function __construct(
        private ?int $id,
        private int $towServiceId,
        private int $userId,
        private int $ratedUserId,
        private int $rating,
        private ?string $comment,
        private ?DateTimeImmutable $createdAt,
    )
    {
        if($rating < 1 || $rating > 5) {
            throw new InvalidArgumentException('El Rating debe estar entre 1 y 5.');
        }
    }

    public static function create(
        int $towServiceId,
        int $userId,
        int $ratedUserId,
        int $rating,
        ?string $comment = null
    ): self
    {
        return new self(
            id: null, // Se asignará automáticamente al persistir en la base de datos
            towServiceId: $towServiceId,
            userId: $userId,
            ratedUserId: $ratedUserId,
            rating: $rating,
            comment: $comment,
            createdAt: new DateTimeImmutable()
        );
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getTowServiceId(): int
    {
        return $this->towServiceId;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getRatedUserId(): int
    {
        return $this->ratedUserId;
    }

    public function getRating(): int
    {
        return $this->rating;
    }   

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

}