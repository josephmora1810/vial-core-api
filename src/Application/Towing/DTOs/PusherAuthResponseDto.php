<?php

declare(strict_types=1);

namespace App\Application\Towing\DTOs;

readonly class PusherAuthResponseDto
{
    public function __construct(
        public string $auth,
        public ?string $channel_data = null
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            auth: (string)($data['auth'] ?? ''),
            channel_data: isset($data['channel_data']) ? (string)$data['channel_data'] : null
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $arr = ['auth' => $this->auth];
        if ($this->channel_data !== null) {
            $arr['channel_data'] = $this->channel_data;
        }
        return $arr;
    }
}
