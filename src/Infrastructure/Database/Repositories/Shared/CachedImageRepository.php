<?php

namespace App\Infrastructure\Database\Repositories\Shared;

use App\Domain\Shared\Repositories\ImageRepositoryInterface;
use App\Domain\Shared\Services\RedisClientInterface;
use Throwable;

class CachedImageRepository implements ImageRepositoryInterface
{
    private const int CACHE_TTL = 86400; // 24 horas en Redis

    public function __construct(
        private ImageRepositoryInterface $inner,
        private ?RedisClientInterface $redis = null
    ) {}

    public function save(string $imageableType, int $imageableId, string $type, string $path): int
    {
        $id = $this->inner->save($imageableType, $imageableId, $type, $path);
        $this->invalidateCache($imageableType, $imageableId);
        return $id;
    }

    public function findByImageable(string $imageableType, int $imageableId, ?string $type = null): array
    {
        $key = $this->buildListKey($imageableType, $imageableId, $type);

        if ($this->redis !== null) {
            try {
                $cached = $this->redis->execute('GET', $key);
                if (is_string($cached) && $cached !== '') {
                    $decoded = json_decode($cached, true);
                    if (is_array($decoded)) {
                        return $decoded;
                    }
                }
            } catch (Throwable) {
                // Fallback silencioso a base de datos
            }
        }

        $items = $this->inner->findByImageable($imageableType, $imageableId, $type);

        if ($this->redis !== null && !empty($items)) {
            try {
                $this->redis->execute('SETEX', $key, (string)self::CACHE_TTL, json_encode($items));
            } catch (Throwable) {
            }
        }

        return $items;
    }

    public function findLatestByImageable(string $imageableType, int $imageableId, ?string $type = null): ?array
    {
        $key = $this->buildSingleKey($imageableType, $imageableId, $type);

        if ($this->redis !== null) {
            try {
                $cached = $this->redis->execute('GET', $key);
                if (is_string($cached) && $cached !== '') {
                    $decoded = json_decode($cached, true);
                    if (is_array($decoded)) {
                        return $decoded;
                    }
                }
            } catch (Throwable) {
            }
        }

        $item = $this->inner->findLatestByImageable($imageableType, $imageableId, $type);

        if ($this->redis !== null && $item !== null) {
            try {
                $this->redis->execute('SETEX', $key, (string)self::CACHE_TTL, json_encode($item));
            } catch (Throwable) {
            }
        }

        return $item;
    }

    public function deleteByImageable(string $imageableType, int $imageableId, ?string $type = null): bool
    {
        $result = $this->inner->deleteByImageable($imageableType, $imageableId, $type);
        $this->invalidateCache($imageableType, $imageableId);
        return $result;
    }

    private function invalidateCache(string $imageableType, int $imageableId): void
    {
        if ($this->redis === null) {
            return;
        }

        try {
            $this->redis->execute('DEL', "images:single:{$imageableType}:{$imageableId}");
            $this->redis->execute('DEL', "images:single:{$imageableType}:{$imageableId}:avatar");
            $this->redis->execute('DEL', "images:single:{$imageableType}:{$imageableId}:gallery_banner");
            $this->redis->execute('DEL', "images:list:{$imageableType}:{$imageableId}");
            if ($imageableType === 'home_gallery') {
                $this->redis->execute('DEL', 'catalog:home_gallery:active');
            }
        } catch (Throwable) {
        }
    }

    private function buildSingleKey(string $imageableType, int $imageableId, ?string $type): string
    {
        $key = "images:single:{$imageableType}:{$imageableId}";
        return $type !== null ? "{$key}:{$type}" : $key;
    }

    private function buildListKey(string $imageableType, int $imageableId, ?string $type): string
    {
        $key = "images:list:{$imageableType}:{$imageableId}";
        return $type !== null ? "{$key}:{$type}" : $key;
    }
}
