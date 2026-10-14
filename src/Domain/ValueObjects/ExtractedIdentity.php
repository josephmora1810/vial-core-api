<?php

namespace App\Domain\ValueObjects;

readonly class ExtractedIdentity
{
    public function __construct(
        private NationalId $nationalId,
        private Names $names,
        private Surnames $surnames,
        private ?BirthDate $birthDate,
        private float $confidenceScore,
        private string $status,
        private array $rawPayload = []
    ) {
    }

    public function getNationalId(): NationalId
    {
        return $this->nationalId;
    }

    public function getNames(): Names
    {
        return $this->names;
    }

    public function getSurnames(): Surnames
    {
        return $this->surnames;
    }

    public function getBirthDate(): ?BirthDate
    {
        return $this->birthDate;
    }

    public function getConfidenceScore(): float
    {
        return $this->confidenceScore;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getRawPayload(): array
    {
        return $this->rawPayload;
    }
}