<?php

namespace App\Domain\Auth\ValueObjects;





use App\Domain\Users\ValueObjects\Surnames;use App\Domain\Users\ValueObjects\NationalId;use App\Domain\Users\ValueObjects\Names;use App\Domain\Users\ValueObjects\BirthDate;readonly class ExtractedIdentity
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