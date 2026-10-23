<?php

namespace Tests\Unit\Infrastructure;

use App\Core\DotEnv;
use App\Domain\Shared\ValueObjects\Coordinates;
use App\Domain\Shared\ValueObjects\Uuid;
use App\Domain\Towing\Entities\Rating;
use App\Domain\Towing\Entities\TowService;
use App\Domain\Towing\Entities\TowServiceLog;
use App\Domain\Towing\Enums\TowServiceStatus;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Repositories\Towing\MariaDbRatingRepository;
use App\Infrastructure\Database\Repositories\Towing\MariaDbTowServiceLogRepository;
use App\Infrastructure\Database\Repositories\Towing\MariaDbTowServiceRepository;
use PDO;
use PHPUnit\Framework\TestCase;

class MariaDbTowingRepositoriesTest extends TestCase
{
    private PDO $pdo;
    private MariaDbTowServiceRepository $towRepo;
    private MariaDbTowServiceLogRepository $logRepo;
    private MariaDbRatingRepository $ratingRepo;

    private int $clientId;
    private int $driverId;
    private int $vehicleId;

    protected function setUp(): void
    {
        $envFile = dirname(__DIR__, 3) . '/.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
                    continue;
                }
                [$k, $v] = explode('=', $line, 2);
                $k = trim($k);
                $v = trim($v, " \t\n\r\0\x0B\"");
                $_ENV[$k] = $v;
                $_SERVER[$k] = $v;
            }
        }

        $this->pdo = (new Database())->getConnection();
        $this->pdo->beginTransaction();

        $this->towRepo = new MariaDbTowServiceRepository($this->pdo);
        $this->logRepo = new MariaDbTowServiceLogRepository($this->pdo);
        $this->ratingRepo = new MariaDbRatingRepository($this->pdo);

        // Obtenemos IDs reales de usuarios y vehículos existentes para satisfacer las FKs
        $userStmt = $this->pdo->query('SELECT id FROM users ORDER BY id ASC LIMIT 2');
        $users = $userStmt->fetchAll(PDO::FETCH_COLUMN);

        if (count($users) < 2) {
            $this->pdo->exec("INSERT INTO users (uuid, names, surnames, identification_card, birth_date, phone, email, password) VALUES 
                ('00000000-0000-0000-0000-000000000001', 'Client', 'Test', 'V-11111111', '1990-01-01', '+584120000001', 'client.towing@example.com', 'hash'),
                ('00000000-0000-0000-0000-000000000002', 'Driver', 'Test', 'V-22222222', '1990-01-01', '+584120000002', 'driver.towing@example.com', 'hash')
                ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
            $userStmt = $this->pdo->query('SELECT id FROM users ORDER BY id ASC LIMIT 2');
            $users = $userStmt->fetchAll(PDO::FETCH_COLUMN);
        }

        $this->clientId = (int)$users[0];
        $this->driverId = (int)($users[1] ?? $this->clientId);

        $vehStmt = $this->pdo->query('SELECT id FROM vehicles ORDER BY id ASC LIMIT 1');
        $vehId = $vehStmt->fetchColumn();

        if (!$vehId) {
            $modelId = (int)$this->pdo->query('SELECT id FROM vehicle_models LIMIT 1')->fetchColumn();
            if (!$modelId) {
                $modelId = 1;
            }
            $this->pdo->exec("INSERT INTO vehicles (uuid, user_id, model_id, plate_number, niv, year, color, status, is_verified) 
                VALUES ('00000000-0000-0000-0000-000000000010', {$this->clientId}, $modelId, 'TEST-123', 'TESTNIV1234567890', 2020, 'Blanco', 'active', 1)
                ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
            $vehStmt = $this->pdo->query('SELECT id FROM vehicles ORDER BY id ASC LIMIT 1');
            $vehId = $vehStmt->fetchColumn();
        }

        $this->vehicleId = (int)$vehId;
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo) && $this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    public function testCanInsertFindUpdateAndLockTowService(): void
    {
        $origin = new Coordinates(10.4806, -66.9036);
        $dest = new Coordinates(10.6000, -66.9500);

        $service = TowService::create(
            clientId: $this->clientId,
            vehicleId: $this->vehicleId,
            origin: $origin,
            originAddress: 'Av. Libertador, Caracas',
            destination: $dest,
            destinationAddress: 'Catia La Mar, La Guaira',
            failureDescription: 'Batería descargada',
            estimatedDistanceKm: 28.4
        );

        // 1. Guardar nuevo servicio (INSERT)
        $this->towRepo->save($service);
        $this->assertNotNull($service->getId());
        $this->assertGreaterThan(0, $service->getId());

        // 2. Buscar por ID
        $found = $this->towRepo->findById($service->getId());
        $this->assertNotNull($found);
        $this->assertSame($service->getId(), $found->getId());
        $this->assertSame('Av. Libertador, Caracas', $found->getOriginAddress());
        $this->assertSame('Catia La Mar, La Guaira', $found->getDestinationAddress());
        $this->assertSame(TowServiceStatus::Pending, $found->getStatus());

        // 3. Buscar por UUID
        $foundByUuid = $this->towRepo->findByUuid($service->getUuid());
        $this->assertNotNull($foundByUuid);
        $this->assertSame($service->getId(), $foundByUuid->getId());

        // 4. Bloqueo pesimista FOR UPDATE
        $locked = $this->towRepo->lockForUpdate($service->getId());
        $this->assertNotNull($locked);

        // 5. Aceptar servicio y actualizar (UPDATE)
        $locked->accept($this->driverId);
        $this->towRepo->save($locked);

        $updated = $this->towRepo->findById($service->getId());
        $this->assertSame(TowServiceStatus::InRoute, $updated->getStatus());
        $this->assertSame($this->driverId, $updated->getDriverId());
        $this->assertNotNull($updated->getAcceptedAt());

        // 6. Buscar activo por conductor
        $activeDriverService = $this->towRepo->findActiveByDriverId($this->driverId);
        $this->assertNotNull($activeDriverService);
        $this->assertSame($service->getId(), $activeDriverService->getId());
    }

    public function testCanFindPendingNearbyWithHaversineFormula(): void
    {
        $origin = new Coordinates(10.4806, -66.9036);
        $dest = new Coordinates(10.5000, -66.9200);

        $service = TowService::create(
            clientId: $this->clientId,
            vehicleId: $this->vehicleId,
            origin: $origin,
            originAddress: 'Chacao, Caracas',
            destination: $dest,
            destinationAddress: 'Las Mercedes, Caracas',
            failureDescription: 'Caucho espichado',
            estimatedDistanceKm: 5.0
        );
        $this->towRepo->save($service);

        // Buscar a 15 km de Plaza Venezuela
        $searchCenter = new Coordinates(10.4960, -66.8850);
        $nearby = $this->towRepo->findPendingNearby($searchCenter, radiusKm: 15.0);

        $this->assertNotEmpty($nearby);
        $ids = array_map(fn(TowService $s) => $s->getId(), $nearby);
        $this->assertContains($service->getId(), $ids);
    }

    public function testCanAppendAndQueryTowServiceLogs(): void
    {
        $service = TowService::create(
            clientId: $this->clientId,
            vehicleId: $this->vehicleId,
            origin: new Coordinates(10.48, -66.90),
            originAddress: 'Origen',
            destination: new Coordinates(10.50, -66.91),
            destinationAddress: 'Destino',
            failureDescription: 'Test logs',
            estimatedDistanceKm: 10.0
        );
        $this->towRepo->save($service);

        $log1 = TowServiceLog::record(
            towServiceId: $service->getId(),
            coordinates: new Coordinates(10.4810, -66.9010),
            speedKmh: 35.0
        );
        $log2 = TowServiceLog::record(
            towServiceId: $service->getId(),
            coordinates: new Coordinates(10.4850, -66.9040),
            speedKmh: 42.5
        );

        $this->logRepo->append($log1);
        $this->logRepo->append($log2);

        $this->assertNotNull($log1->getId());
        $this->assertNotNull($log2->getId());

        $latest = $this->logRepo->getLatestByServiceId($service->getId());
        $this->assertNotNull($latest);
        $this->assertSame($log2->getId(), $latest->getId());
        $this->assertSame(42.5, $latest->getSpeedKmh());

        $history = $this->logRepo->getHistoryByServiceId($service->getId());
        $this->assertCount(2, $history);
    }

    public function testCanSaveAndCalculateRatings(): void
    {
        $service = TowService::create(
            clientId: $this->clientId,
            vehicleId: $this->vehicleId,
            origin: new Coordinates(10.48, -66.90),
            originAddress: 'Origen',
            destination: new Coordinates(10.50, -66.91),
            destinationAddress: 'Destino',
            failureDescription: 'Test rating',
            estimatedDistanceKm: 8.0
        );
        $this->towRepo->save($service);

        $rating = Rating::create(
            towServiceId: $service->getId(),
            userId: $this->clientId,
            ratedUserId: $this->driverId,
            rating: 5,
            comment: 'Excelente servicio'
        );
        $this->ratingRepo->save($rating);
        $this->assertNotNull($rating->getId());

        $found = $this->ratingRepo->findByServiceAndUser($service->getId(), $this->clientId);
        $this->assertNotNull($found);
        $this->assertSame(5, $found->getRating());
        $this->assertSame('Excelente servicio', $found->getComment());

        $avg = $this->ratingRepo->getAverageRatingForUser($this->driverId);
        $this->assertGreaterThanOrEqual(1.0, $avg);
        $this->assertLessThanOrEqual(5.0, $avg);
    }
}
