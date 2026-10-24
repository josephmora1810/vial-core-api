<?php

namespace Tests\Unit\Infrastructure;

use App\Domain\Driver\Entities\DriverShift;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Repositories\Driver\MariaDbDriverShiftRepository;
use PDO;
use PHPUnit\Framework\TestCase;
use Throwable;

class DriverInfrastructureTest extends TestCase
{
    private ?PDO $pdo = null;
    private bool $inTransaction = false;
    private ?MariaDbDriverShiftRepository $repository = null;

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
            $this->pdo->exec("SET FOREIGN_KEY_CHECKS=0;");
            $this->repository = new MariaDbDriverShiftRepository($this->pdo);
        } catch (Throwable) {
            $this->pdo = null;
            $this->repository = null;
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

    public function testSaveAndFindActiveShift(): void
    {
        if ($this->repository === null || $this->pdo === null) {
            $this->markTestSkipped('Conexión MariaDB no disponible.');
        }

        $shift = DriverShift::start(driverId: 99991, craneId: 88881);
        $saved = $this->repository->save($shift);

        $this->assertNotNull($saved->getId());
        $this->assertSame(99991, $saved->getDriverId());
        $this->assertTrue($saved->isActive());

        $found = $this->repository->findActiveByDriverId(99991);
        $this->assertNotNull($found);
        $this->assertSame($saved->getId(), $found->getId());

        // Actualizar coordenadas por driverId
        $this->repository->updateLocation(99991, 10.4900, -66.8800);
        $updated = $this->repository->findActiveByDriverId(99991);
        $this->assertNotNull($updated);
        $this->assertEqualsWithDelta(10.4900, $updated->getLastLatitude(), 0.0001);
        $this->assertEqualsWithDelta(-66.8800, $updated->getLastLongitude(), 0.0001);

        // Desactivar turno activo
        $this->repository->endActiveShift(99991);
        $activeAfterEnd = $this->repository->findActiveByDriverId(99991);
        $this->assertNull($activeAfterEnd);
    }
}
