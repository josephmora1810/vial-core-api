<?php

declare(strict_types=1);

namespace App\Application\Towing\DTOs;

readonly class PusherWebhookResponseDto
{
    /**
     * @param list<array{event: string, channel: string}> $events
     */
    public function __construct(
        public int $processed,
        public array $events
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            processed: (int)($data['processed'] ?? 0),
            events: (array)($data['events'] ?? [])
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'processed' => $this->processed,
            'events'    => $this->events,
        ];
    }
}
