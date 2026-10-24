<?php

declare(strict_types=1);

namespace App\Application\Towing\DTOs;

readonly class CancellationReasonsResponseDto
{
    /**
     * @param list<array<string, string>>|null $reasons
     * @param list<array<string, string>>|null $client
     * @param list<array<string, string>>|null $driver
     */
    public function __construct(
        public ?array $reasons = null,
        public ?array $client = null,
        public ?array $driver = null
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            reasons: isset($data['reasons']) ? (array)$data['reasons'] : null,
            client: isset($data['client']) ? (array)$data['client'] : null,
            driver: isset($data['driver']) ? (array)$data['driver'] : null
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $res = [];
        if ($this->reasons !== null) {
            $res['reasons'] = $this->reasons;
        }
        if ($this->client !== null) {
            $res['client'] = $this->client;
        }
        if ($this->driver !== null) {
            $res['driver'] = $this->driver;
        }
        return $res;
    }
}
