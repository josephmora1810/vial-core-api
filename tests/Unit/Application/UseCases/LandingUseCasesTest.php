<?php

namespace Tests\Unit\Application\UseCases;

use App\Application\Landing\UseCases\{GetActiveHomeGallery, ManageHomeGallery};
use Exception;
use PHPUnit\Framework\TestCase;
use Tests\Doubles\{InMemoryHomeGalleryRepository, InMemoryStorageService};

class LandingUseCasesTest extends TestCase
{
    private InMemoryHomeGalleryRepository $repo;
    private InMemoryStorageService $storage;
    private ManageHomeGallery $manage;
    private GetActiveHomeGallery $getActive;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        $this->repo = new InMemoryHomeGalleryRepository();
        $this->storage = new InMemoryStorageService();
        $this->manage = new ManageHomeGallery($this->repo, $this->storage);
        $this->getActive = new GetActiveHomeGallery($this->repo);
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    private function tempFile(string $ext = 'jpg'): string
    {
        $path = sys_get_temp_dir() . '/landing_uc_' . bin2hex(random_bytes(4)) . '.' . $ext;
        file_put_contents($path, 'FAKE_IMAGE');
        $this->tempFiles[] = $path;
        return $path;
    }

    public function testCreateRequiresTitle(): void
    {
        try {
            $this->manage->create(title: '   ');
            $this->fail('Expected exception');
        } catch (Exception $e) {
            $this->assertSame(422, $e->getCode());
            $this->assertSame('El título del banner de galería es obligatorio.', $e->getMessage());
        }
    }

    public function testCreateWithImageUrlReturnsEntity(): void
    {
        $item = $this->manage->create(title: ' Hola ', imageUrl: 'https://cdn/x.jpg', orderIndex: 2);

        $this->assertSame(1, $item->getId());
        $this->assertSame('Hola', $item->getTitle());
        $this->assertSame('https://cdn/x.jpg', $item->getImageUrl());
        $this->assertSame([], $this->storage->storage);
    }

    public function testCreateUploadsLocalFileAndDeletesIt(): void
    {
        $file = $this->tempFile('PNG');

        $item = $this->manage->create(title: 'T', localImageFilePath: $file);

        $this->assertCount(1, $this->storage->storage);
        $key = array_key_first($this->storage->storage);
        $this->assertMatchesRegularExpression('#^gallery/banner_[0-9a-f]{12}\.png$#', $key);
        $this->assertSame($this->storage->baseUrl . '/' . $key, $item->getImageUrl());
        $this->assertFileDoesNotExist($file);
    }

    public function testCreateDefaultsExtensionToJpg(): void
    {
        $path = sys_get_temp_dir() . '/landing_noext_' . bin2hex(random_bytes(4));
        file_put_contents($path, 'x');
        $this->tempFiles[] = $path;

        $this->manage->create(title: 'T', localImageFilePath: $path);

        $this->assertStringEndsWith('.jpg', (string)array_key_first($this->storage->storage));
    }

    public function testCreateMissingLocalFileFails400(): void
    {
        try {
            $this->manage->create(title: 'T', localImageFilePath: sys_get_temp_dir() . '/does_not_exist_' . bin2hex(random_bytes(4)) . '.jpg');
            $this->fail('Expected exception');
        } catch (Exception $e) {
            $this->assertSame(400, $e->getCode());
            $this->assertSame('El archivo de imagen no fue encontrado o es ilegible.', $e->getMessage());
        }
    }

    public function testCreatePathOutsideStorageAndTempIsDenied403(): void
    {
        $outside = dirname(__DIR__, 4) . '/composer.json';
        if (!is_file($outside)) {
            $outside = dirname(__DIR__, 4) . '/phpunit.xml';
        }
        $this->assertFileExists($outside);

        try {
            $this->manage->create(title: 'T', localImageFilePath: $outside);
            $this->fail('Expected exception');
        } catch (Exception $e) {
            $this->assertSame(403, $e->getCode());
            $this->assertSame('Acceso denegado: la ruta del archivo no está autorizada para carga de imágenes.', $e->getMessage());
        }
        $this->assertFileExists($outside);
        $this->assertSame([], $this->storage->storage);
    }

    public function testUpdateNotFound(): void
    {
        try {
            $this->manage->update(id: 999, title: 'x');
            $this->fail('Expected exception');
        } catch (Exception $e) {
            $this->assertSame(404, $e->getCode());
            $this->assertSame('Banner de galería no encontrado.', $e->getMessage());
        }
    }

    public function testUpdateAppliesOnlyProvidedFields(): void
    {
        $created = $this->manage->create(title: 'Old', subtitle: 'Sub', orderIndex: 5, isActive: true);

        $updated = $this->manage->update(
            id: $created->getId(),
            title: 'New',
            buttonText: 'Go',
            buttonLink: '/x',
            description: 'D',
            isActive: false
        );

        $this->assertSame('New', $updated->getTitle());
        $this->assertSame('Sub', $updated->getSubtitle());
        $this->assertSame('D', $updated->getDescription());
        $this->assertSame('Go', $updated->getButtonText());
        $this->assertSame('/x', $updated->getButtonLink());
        $this->assertSame(5, $updated->getOrderIndex());
        $this->assertFalse($updated->isActive());

        $reactivated = $this->manage->update(id: $created->getId(), isActive: true, subtitle: 'S2', orderIndex: 1);
        $this->assertTrue($reactivated->isActive());
        $this->assertSame('S2', $reactivated->getSubtitle());
        $this->assertSame(1, $reactivated->getOrderIndex());
    }

    public function testUpdateWithLocalFileUploadsAndReplacesImage(): void
    {
        $created = $this->manage->create(title: 'T', imageUrl: 'https://old/img.jpg');
        $file = $this->tempFile('webp');

        $updated = $this->manage->update(id: $created->getId(), localImageFilePath: $file);

        $this->assertStringStartsWith($this->storage->baseUrl . '/gallery/banner_', (string)$updated->getImageUrl());
        $this->assertStringEndsWith('.webp', (string)$updated->getImageUrl());
        $this->assertFileDoesNotExist($file);
    }

    public function testUpdateWithImageUrlPersistsIt(): void
    {
        $created = $this->manage->create(title: 'T');

        $updated = $this->manage->update(id: $created->getId(), imageUrl: 'https://new/img.jpg');

        $this->assertSame('https://new/img.jpg', $this->repo->findById($created->getId())->getImageUrl());
        $this->assertSame('https://new/img.jpg', $updated->getImageUrl());
    }

    public function testUpdatePathOutsideStorageIsDenied403(): void
    {
        $created = $this->manage->create(title: 'T');
        $outside = dirname(__DIR__, 4) . '/phpunit.xml';
        if (!is_file($outside)) {
            $outside = dirname(__DIR__, 4) . '/composer.json';
        }

        try {
            $this->manage->update(id: $created->getId(), localImageFilePath: $outside);
            $this->fail('Expected exception');
        } catch (Exception $e) {
            $this->assertSame(403, $e->getCode());
        }
        $this->assertFileExists($outside);
    }

    public function testDeleteReturnsDtoAndRemovesItem(): void
    {
        $created = $this->manage->create(title: 'T');

        $result = $this->manage->delete($created->getId());

        $this->assertTrue($result->deleted);
        $this->assertSame($created->getId(), $result->id);
        $this->assertNull($this->repo->findById($created->getId()));
    }

    public function testDeleteNotFound(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionCode(404);
        $this->expectExceptionMessage('Banner de galería no encontrado.');
        $this->manage->delete(123);
    }

    public function testListAllReturnsEntitiesOrderedByOrderIndex(): void
    {
        $this->manage->create(title: 'B', orderIndex: 2, isActive: false);
        $this->manage->create(title: 'A', orderIndex: 1);

        $all = $this->manage->listAll();

        $this->assertCount(2, $all);
        $this->assertSame('A', $all[0]->getTitle());
        $this->assertSame('B', $all[1]->getTitle());
    }

    public function testGetActiveReturnsOnlyActiveOrdered(): void
    {
        $this->assertSame([], $this->getActive->execute());

        $this->manage->create(title: 'Second', orderIndex: 2);
        $this->manage->create(title: 'Hidden', orderIndex: 0, isActive: false);
        $this->manage->create(title: 'First', orderIndex: 1);

        $active = $this->getActive->execute();

        $this->assertCount(2, $active);
        $this->assertSame('First', $active[0]->getTitle());
        $this->assertSame('Second', $active[1]->getTitle());
    }
}
