<?php

namespace Tests\Unit\Infrastructure;

use App\Domain\Auth\Entities\{Document, PersonalAccessToken};
use App\Domain\Auth\Enums\{DocumentStatus, DocumentType};
use App\Domain\Shared\ValueObjects\Uuid;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Repositories\Auth\{
    MariaDbDocumentRepository,
    MariaDbPersonalAccessTokenRepository
};
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

class MariaDbAuthRepositoriesTest extends TestCase
{
    private PDO $pdo;
    private MariaDbDocumentRepository $docRepo;
    private MariaDbPersonalAccessTokenRepository $tokenRepo;
    private int $userId;
    private string $userUuid;
    private ?int $vehicleId = null;

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

        $this->docRepo = new MariaDbDocumentRepository($this->pdo);
        $this->tokenRepo = new MariaDbPersonalAccessTokenRepository($this->pdo);

        // Obtener un usuario de prueba existente o crear uno
        $userStmt = $this->pdo->query('SELECT id, uuid FROM users ORDER BY id ASC LIMIT 1');
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $this->userUuid = '018e3d2f-9999-7123-8123-0123456789aa';
            $this->pdo->exec("INSERT INTO users (uuid, names, surnames, identification_card, birth_date, phone, email, password) VALUES 
                ('{$this->userUuid}', 'Auth', 'Tester', 'V-99999999', '1990-01-01', '+584129999999', 'auth.tester@ubikgo.com', 'hash')");
            $this->userId = (int)$this->pdo->lastInsertId();
        } else {
            $this->userId = (int)$user['id'];
            $this->userUuid = (string)$user['uuid'];
        }

        // Obtener o crear vehículo para el usuario
        $vehStmt = $this->pdo->prepare('SELECT id FROM vehicles WHERE user_id = :uid LIMIT 1');
        $vehStmt->execute(['uid' => $this->userId]);
        $vehId = $vehStmt->fetchColumn();

        if ($vehId) {
            $this->vehicleId = (int)$vehId;
        } else {
            $modelId = (int)$this->pdo->query('SELECT id FROM vehicle_models LIMIT 1')->fetchColumn();
            if ($modelId) {
                $vehUuid = '018e3d2f-8888-7123-8123-0123456789bb';
                $this->pdo->exec("INSERT INTO vehicles (uuid, user_id, model_id, plate_number, niv, year, color, status, is_verified) 
                    VALUES ('$vehUuid', {$this->userId}, $modelId, 'AB123CD', 'NIVAUTH1234567890', 2021, 'Gris', 'active', 0)");
                $this->vehicleId = (int)$this->pdo->lastInsertId();
            }
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo) && $this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    public function testCanInsertFindUpdateAndCheckStatusInDocumentRepository(): void
    {
        // 1. Inserción
        $doc = Document::create(
            documentableType: 'user',
            documentableId: $this->userId,
            documentType: DocumentType::IdentityCard,
            filePath: '/tmp/id_test.jpg',
            extractedData: ['national_id' => 'V-99999999', 'names' => 'Auth Tester'],
            status: DocumentStatus::Pending
        );

        $this->docRepo->save($doc);
        $this->assertNotNull($doc->getId());
        $docId = (int)$doc->getId();

        // 2. Consulta por ID
        $found = $this->docRepo->findById($docId);
        $this->assertNotNull($found);
        $this->assertSame('user', $found->getDocumentableType());
        $this->assertSame($this->userId, $found->getDocumentableId());
        $this->assertSame(DocumentType::IdentityCard, $found->getDocumentType());
        $this->assertSame('/tmp/id_test.jpg', $found->getFilePath());
        $this->assertSame('V-99999999', $found->getExtractedData()['national_id']);
        $this->assertSame(DocumentStatus::Pending, $found->getStatus());

        // 3. Consulta de último documento por entidad y tipo
        $latest = $this->docRepo->findLatestByEntity('user', $this->userId, DocumentType::IdentityCard);
        $this->assertNotNull($latest);
        $this->assertSame($docId, $latest->getId());

        // 4. Consulta de todos los documentos por entidad
        $all = $this->docRepo->findAllByEntity('user', $this->userId);
        $this->assertNotEmpty($all);

        // 5. Consulta por estado PENDING
        $pendingDocs = $this->docRepo->findByStatus('PENDING');
        $this->assertNotEmpty($pendingDocs);

        // 6. Actualización (Aprobar documento)
        $found->verify();
        $this->docRepo->save($found);

        $reloaded = $this->docRepo->findById($docId);
        $this->assertTrue($reloaded->isVerified());

        // 7. Comprobar isUserIdentityVerified
        $this->assertTrue($this->docRepo->isUserIdentityVerified($this->userId));
    }

    public function testHasVerifiedVehicleDocumentChecksVehicleRegistration(): void
    {
        if (!$this->vehicleId) {
            $this->markTestSkipped('No vehicle available for vehicle document test.');
        }

        $vehDoc = Document::create(
            documentableType: 'vehicle',
            documentableId: $this->vehicleId,
            documentType: DocumentType::VehicleRegistration,
            filePath: '/tmp/carnet_test.jpg',
            status: DocumentStatus::Verified
        );

        $this->docRepo->save($vehDoc);
        $this->assertTrue($this->docRepo->hasVerifiedVehicleDocument($this->userId));
    }

    public function testPersonalAccessTokenRepositoryFullCycle(): void
    {
        $plainToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $plainToken);
        $expiresAt = (new DateTimeImmutable())->modify('+7 days');

        $token = PersonalAccessToken::create(
            userId: $this->userId,
            plainToken: $plainToken,
            expiresAt: $expiresAt
        );

        // 1. Guardar token
        $this->tokenRepo->save($token);
        $this->assertNotNull($token->getId());
        $tokenId = (int)$token->getId();

        // 2. Buscar por hash
        $found = $this->tokenRepo->findByTokenHash($tokenHash);
        $this->assertNotNull($found);
        $this->assertSame($tokenId, $found->getId());
        $this->assertSame($this->userId, $found->getUserId());
        $this->assertSame($tokenHash, $found->getTokenHash());
        $this->assertFalse($found->isRevoked());
        $this->assertTrue($found->isValid());

        // 3. Resolver UUID del usuario asociado al token válido
        $resolvedUuid = $this->tokenRepo->findUserUuidByTokenHash($tokenHash);
        $this->assertNotNull($resolvedUuid);
        $this->assertSame($this->userUuid, $resolvedUuid->getValue());

        // 4. Revocar token por hash
        $this->tokenRepo->revokeByTokenHash($tokenHash);
        $revoked = $this->tokenRepo->findByTokenHash($tokenHash);
        $this->assertTrue($revoked->isRevoked());
        $this->assertFalse($revoked->isValid());

        // 5. findUserUuidByTokenHash debe retornar null cuando el token está revocado
        $revokedUuid = $this->tokenRepo->findUserUuidByTokenHash($tokenHash);
        $this->assertNull($revokedUuid);

        // 6. Emitir otro token y revocar todos para el usuario
        $secondPlain = bin2hex(random_bytes(32));
        $secondToken = PersonalAccessToken::create(
            userId: $this->userId,
            plainToken: $secondPlain,
            expiresAt: $expiresAt
        );
        $this->tokenRepo->save($secondToken);

        $this->tokenRepo->revokeAllForUserId($this->userId);
        $reloadedSecond = $this->tokenRepo->findByTokenHash(hash('sha256', $secondPlain));
        $this->assertTrue($reloadedSecond->isRevoked());
    }
}
