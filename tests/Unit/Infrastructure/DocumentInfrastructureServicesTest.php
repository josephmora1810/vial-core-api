<?php

namespace Tests\Unit\Infrastructure;

use App\Domain\Shared\Repositories\ImageRepositoryInterface;
use App\Infrastructure\Database\{Database, Redis};
use App\Infrastructure\Database\Repositories\Shared\{
    CachedImageRepository,
    MariaDbImageRepository
};
use App\Infrastructure\Storage\{
    AwsS3StorageException,
    AwsS3StorageService
};
use PDO;
use PHPUnit\Framework\TestCase;
use Throwable;

class DocumentInfrastructureServicesTest extends TestCase
{
    private ?PDO $pdo = null;
    private bool $inTransaction = false;
    private ?MariaDbImageRepository $imageRepo = null;

    protected function setUp(): void
    {
        $envPath = dirname(__DIR__, 3) . '/.env';
        if (file_exists($envPath) && empty($_ENV['DB_HOST'])) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
                    continue;
                }
                [$name, $value] = explode('=', $line, 2);
                $_ENV[trim($name)] = trim($value);
            }
        }

        try {
            $this->pdo = (new Database())->getConnection();
            $this->pdo->beginTransaction();
            $this->inTransaction = true;
            $this->imageRepo = new MariaDbImageRepository($this->pdo);
        } catch (Throwable $e) {
            $this->pdo = null;
            $this->imageRepo = null;
        }
    }

    protected function tearDown(): void
    {
        if ($this->pdo !== null && $this->inTransaction) {
            try {
                $this->pdo->rollBack();
            } catch (Throwable) {
            }
            $this->inTransaction = false;
        }
    }

    public function testMariaDbImageRepositorySaveAndRetrieve(): void
    {
        if ($this->imageRepo === null) {
            $this->markTestSkipped('Database not available');
        }

        $id1 = $this->imageRepo->save(
            imageableType: 'document',
            imageableId: 99999,
            type: 'identity_card',
            path: 'https://s3.amazonaws.com/bucket/doc1.jpg'
        );
        $this->assertGreaterThan(0, $id1);

        $latest = $this->imageRepo->findLatestByImageable('document', 99999);
        $this->assertNotNull($latest);
        $this->assertSame('https://s3.amazonaws.com/bucket/doc1.jpg', $latest['path']);

        // Overwrite 1-to-1
        $id2 = $this->imageRepo->save(
            imageableType: 'document',
            imageableId: 99999,
            type: 'identity_card',
            path: 'https://s3.amazonaws.com/bucket/doc1_updated.jpg'
        );
        $this->assertSame($id1, $id2);

        $updated = $this->imageRepo->findLatestByImageable('document', 99999);
        $this->assertSame('https://s3.amazonaws.com/bucket/doc1_updated.jpg', $updated['path']);

        $deleted = $this->imageRepo->deleteByImageable('document', 99999);
        $this->assertTrue($deleted);

        $afterDelete = $this->imageRepo->findLatestByImageable('document', 99999);
        $this->assertNull($afterDelete);
    }

    public function testCachedImageRepositoryDelegatesAndCaches(): void
    {
        $mockInner = $this->createMock(ImageRepositoryInterface::class);
        $mockRedis = $this->createMock(Redis::class);

        $cachedRepo = new CachedImageRepository($mockInner, $mockRedis);

        // Expect save() on inner and DEL on redis
        $mockInner->expects($this->once())
            ->method('save')
            ->with('document', 10, 'avatar', 'https://s3/avatar.jpg')
            ->willReturn(77);

        $mockRedis->expects($this->atLeastOnce())
            ->method('execute')
            ->with('DEL', $this->callback(fn($k) => is_string($k)));

        $savedId = $cachedRepo->save('document', 10, 'avatar', 'https://s3/avatar.jpg');
        $this->assertSame(77, $savedId);
    }

    public function testAwsS3StorageServiceNonExistentFileThrowsException(): void
    {
        $service = new AwsS3StorageService(
            accessKey: 'dummy-key',
            secretKey: 'dummy-secret',
            region: 'us-east-1',
            bucket: 'test-bucket'
        );

        $this->expectException(AwsS3StorageException::class);
        $this->expectExceptionMessage('no existe o no se puede leer');

        $service->upload('non_existent_file_path_12345.jpg', 'documents/test.jpg');
    }

    public function testAwsS3StorageServiceBaseUrlAndDefaults(): void
    {
        $service = new AwsS3StorageService(
            accessKey: 'key',
            secretKey: 'secret',
            region: 'sa-east-1',
            bucket: 'my-bucket',
            baseUrl: 'https://cdn.ubikgo.com'
        );

        $this->assertInstanceOf(AwsS3StorageService::class, $service);
    }
}
