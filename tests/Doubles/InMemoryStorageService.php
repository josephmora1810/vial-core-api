<?php

namespace Tests\Doubles;

use App\Domain\Shared\Services\StorageServiceInterface;
use RuntimeException;

class InMemoryStorageService implements StorageServiceInterface
{
    /** @var array<string, string> storageKey => content */
    public array $storage = [];
    public bool $shouldFail = false;
    public string $failureMessage = 'S3 connection error';
    public string $baseUrl = 'https://ubik-go.s3.us-east-1.amazonaws.com';

    public function upload(string $localFilePath, string $storageKey, ?string $contentType = null): string
    {
        if ($this->shouldFail) {
            throw new RuntimeException($this->failureMessage);
        }

        $content = file_exists($localFilePath) ? file_get_contents($localFilePath) : 'mock_content';
        $this->storage[$storageKey] = $content ?: '';

        return rtrim($this->baseUrl, '/') . '/' . ltrim($storageKey, '/');
    }

    public function delete(string $storageKey): bool
    {
        unset($this->storage[$storageKey]);
        return true;
    }

    public function exists(string $storageKey): bool
    {
        return isset($this->storage[$storageKey]);
    }
}
