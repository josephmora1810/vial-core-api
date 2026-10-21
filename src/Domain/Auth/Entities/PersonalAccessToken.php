<?php

namespace App\Domain\Auth\Entities;

use DateTimeImmutable;

/**
 * Entidad que representa un Refresh Token de sesión persistido en base de datos.
 * Maneja el ciclo de vida del token y su revocación.
 */
class PersonalAccessToken
{
    public function __construct(
        private ?int $id,
        private int $userId,
        private string $tokenHash,
        private DateTimeImmutable $expiresAt,
        private bool $isRevoked = false,
        private ?DateTimeImmutable $createdAt = null
    ) {
        $this->createdAt ??= new DateTimeImmutable();
    }

    /**
     * Constructor semántico para emitir un nuevo Refresh Token
     */
    public static function create(
        int $userId,
        string $plainToken,
        DateTimeImmutable $expiresAt
    ): self {
        return new self(
            id: null,
            userId: $userId,
            tokenHash: hash('sha256', $plainToken),
            expiresAt: $expiresAt,
            isRevoked: false,
            createdAt: new DateTimeImmutable()
        );
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function getExpiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isRevoked(): bool
    {
        return $this->isRevoked;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Determina si el token sigue siendo usable para refrescar sesión
     */
    public function isValid(): bool
    {
        return !$this->isRevoked && $this->expiresAt > new DateTimeImmutable();
    }

    /**
     * Invalida el token permanentemente
     */
    public function revoke(): self
    {
        $this->isRevoked = true;
        return $this;
    }
}
