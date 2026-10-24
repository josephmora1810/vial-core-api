<?php

namespace App\Application\Payments\DTOs;

readonly class IntegrationLogsResponseDto
{
    /**
     * @param list<array<string, mixed>> $logs
     */
    public function __construct(
        public ?string $filter_service,
        public int $count,
        public array $logs
    ) {}

    /**
     * @param list<array<string, mixed>> $logs
     */
    public static function fromLogs(?string $filterService, array $logs): self
    {
        $logs = array_values($logs);

        return new self($filterService, count($logs), $logs);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $logs = is_array($data['logs'] ?? null) ? array_values($data['logs']) : [];

        return new self(
            filter_service: isset($data['filter_service']) ? (string)$data['filter_service'] : null,
            count: (int)($data['count'] ?? count($logs)),
            logs: $logs
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'filter_service' => $this->filter_service,
            'count'          => $this->count,
            'logs'           => $this->logs,
        ];
    }
}