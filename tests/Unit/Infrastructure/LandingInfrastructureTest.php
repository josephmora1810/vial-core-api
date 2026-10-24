<?php

namespace Tests\Unit\Infrastructure;

use App\Domain\Landing\Entities\HomeGallery;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Repositories\Landing\MariaDbHomeGalleryRepository;
use App\Infrastructure\Database\Repositories\Shared\MariaDbImageRepository;
use PDO;
use PHPUnit\Framework\TestCase;
use Throwable;

class LandingInfrastructureTest extends TestCase
{
    private ?PDO $pdo = null;
    private bool $inTransaction = false;
    private ?MariaDbHomeGalleryRepository $repo = null;
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
            $this->pdo->query('SELECT 1 FROM home_galleries LIMIT 1');
            $this->pdo->query('SELECT 1 FROM images LIMIT 1');
            $this->pdo->beginTransaction();
            $this->inTransaction = true;
            $this->imageRepo = new MariaDbImageRepository($this->pdo);
            $this->repo = new MariaDbHomeGalleryRepository($this->pdo, $this->imageRepo);
        } catch (Throwable $e) {
            $this->pdo = null;
            $this->repo = null;
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

    private function requireDb(): MariaDbHomeGalleryRepository
    {
        if ($this->repo === null) {
            $this->markTestSkipped('Database not available');
        }
        return $this->repo;
    }

    public function testSaveInsertAndFindById(): void
    {
        $repo = $this->requireDb();
        $item = HomeGallery::create(
            title: 'Test banner',
            subtitle: 'Sub',
            description: 'Desc',
            buttonText: 'Go',
            buttonLink: '/go',
            orderIndex: 987654,
            isActive: true,
            imageUrl: 'https://cdn.test/banner.jpg'
        );

        $repo->save($item, 'https://cdn.test/banner.jpg');

        $this->assertNotNull($item->getId());
        $found = $repo->findById($item->getId());
        $this->assertNotNull($found);
        $this->assertSame('Test banner', $found->getTitle());
        $this->assertSame('Sub', $found->getSubtitle());
        $this->assertSame('Desc', $found->getDescription());
        $this->assertSame('Go', $found->getButtonText());
        $this->assertSame('/go', $found->getButtonLink());
        $this->assertSame(987654, $found->getOrderIndex());
        $this->assertTrue($found->isActive());
        $this->assertSame('https://cdn.test/banner.jpg', $found->getImageUrl());
        $this->assertNotNull($found->getCreatedAt());
    }

    public function testFindByIdReturnsNullWhenMissing(): void
    {
        $repo = $this->requireDb();

        $this->assertNull($repo->findById(999999999));
    }

    public function testSaveWithoutImageLeavesImageUrlNull(): void
    {
        $repo = $this->requireDb();
        $item = HomeGallery::create(title: 'No image', orderIndex: 987655);

        $repo->save($item);

        $this->assertNull($repo->findById($item->getId())->getImageUrl());
    }

    public function testSaveUpdateChangesFieldsAndReplacesImage(): void
    {
        $repo = $this->requireDb();
        $item = HomeGallery::create(title: 'Original', orderIndex: 987656, imageUrl: 'https://cdn.test/a.jpg');
        $repo->save($item, 'https://cdn.test/a.jpg');

        $item->setTitle('Changed')->setOrderIndex(987657)->deactivate()->setSubtitle('S2');
        $repo->save($item, 'https://cdn.test/b.jpg');

        $found = $repo->findById($item->getId());
        $this->assertSame('Changed', $found->getTitle());
        $this->assertSame('S2', $found->getSubtitle());
        $this->assertSame(987657, $found->getOrderIndex());
        $this->assertFalse($found->isActive());
        $this->assertSame('https://cdn.test/b.jpg', $found->getImageUrl());
    }

    public function testFindAllAndFindAllActiveFilterAndOrder(): void
    {
        $repo = $this->requireDb();
        $second = HomeGallery::create(title: 'Second', orderIndex: 987702);
        $hidden = HomeGallery::create(title: 'Hidden', orderIndex: 987701, isActive: false);
        $first = HomeGallery::create(title: 'First', orderIndex: 987700);
        $repo->save($second);
        $repo->save($hidden);
        $repo->save($first);

        $ids = fn(array $items): array => array_map(fn(HomeGallery $i) => $i->getId(), $items);

        $all = $ids($repo->findAll());
        $active = $ids($repo->findAllActive());

        $this->assertContains($hidden->getId(), $all);
        $this->assertNotContains($hidden->getId(), $active);

        $ownOrder = fn(array $list): array => array_values(array_intersect($list, [$first->getId(), $hidden->getId(), $second->getId()]));
        $this->assertSame([$first->getId(), $hidden->getId(), $second->getId()], $ownOrder($all));
        $this->assertSame([$first->getId(), $second->getId()], $ownOrder($active));
    }

    public function testDeleteRemovesRowAndImage(): void
    {
        $repo = $this->requireDb();
        $item = HomeGallery::create(title: 'To delete', orderIndex: 987658, imageUrl: 'https://cdn.test/d.jpg');
        $repo->save($item, 'https://cdn.test/d.jpg');
        $id = $item->getId();
        $this->assertNotNull($this->imageRepo->findLatestByImageable('home_gallery', $id, 'gallery_banner'));

        $this->assertTrue($repo->delete($id));

        $this->assertNull($repo->findById($id));
        $this->assertNull($this->imageRepo->findLatestByImageable('home_gallery', $id, 'gallery_banner'));
        $this->assertFalse($repo->delete($id));
    }
}
