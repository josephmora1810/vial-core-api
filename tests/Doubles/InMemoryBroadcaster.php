<?php

namespace Tests\Doubles;

use App\Domain\Shared\Services\BroadcasterInterface;

class InMemoryBroadcaster implements BroadcasterInterface
{
    /** @var list<array{channels: string|array<string>, event: string, data: array<string, mixed>}> */
    private array $broadcasts = [];

    public function broadcast(string|array $channels, string $event, array $data): bool
    {
        $this->broadcasts[] = [
            'channels' => $channels,
            'event'    => $event,
            'data'     => $data,
        ];
        return true;
    }

    /**
     * @return list<array{channels: string|array<string>, event: string, data: array<string, mixed>}>
     */
    public function getBroadcasts(): array
    {
        return $this->broadcasts;
    }

    public function hasBroadcast(string $event, ?string $channel = null): bool
    {
        foreach ($this->broadcasts as $b) {
            if ($b['event'] === $event) {
                if ($channel === null) {
                    return true;
                }
                $channels = is_array($b['channels']) ? $b['channels'] : [$b['channels']];
                if (in_array($channel, $channels, true)) {
                    return true;
                }
            }
        }
        return false;
    }

    public function reset(): void
    {
        $this->broadcasts = [];
    }

    public function authorizeChannel(string $channelName, string $socketId, ?array $channelData = null): array
    {
        return [
            'auth' => "inmemory_key:" . hash_hmac('sha256', "{$socketId}:{$channelName}", 'inmemory_secret'),
        ];
    }
}
