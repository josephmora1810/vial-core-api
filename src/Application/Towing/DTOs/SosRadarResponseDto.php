<?php

declare(strict_types=1);

namespace App\Application\Towing\DTOs;

readonly class SosRadarResponseDto
{
    /**
     * @param list<mixed> $services
     */
    public function __construct(
        public array $services
    ) {}

    /**
     * @param list<mixed> $services
     */
    public static function fromList(array $services): self
    {
        return new self(services: $services);
    }

    /**
     * @return array{services: list<mixed>}
     */
    public function toArray(): array
    {
        return [
            'services' => $this->services,
        ];
    }
}
