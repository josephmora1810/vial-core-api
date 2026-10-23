<?php

namespace Tests\Unit\Infrastructure;

use App\Infrastructure\Database\Migrator;
use PDO;
use PHPUnit\Framework\TestCase;

class MigratorTest extends TestCase
{
    private PDO $pdo;
    private string $tempDir;
    private string $testRunId;

    protected function setUp(): void
    {
        $this->testRunId = bin2hex(random_bytes(4));
        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $this->tempDir = sys_get_temp_dir() . '/vial_migrator_test_' . $this->testRunId;
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->tempDir);
        }
    }

    public function testInitialTableCreationHasStepColumn(): void
    {
        $migrator = new Migrator($this->pdo, $this->tempDir, silent: true);
        $this->assertSame(0, $migrator->getCurrentStep());
        $this->assertSame(1, $migrator->getNextStep());

        $migrator->createMigrationTable();

        $stmt = $this->pdo->query("PRAGMA table_info(migrations)");
        $columns = $stmt->fetchAll();
        $colNames = array_column($columns, 'name');

        $this->assertContains('id', $colNames);
        $this->assertContains('migration', $colNames);
        $this->assertContains('step', $colNames);
        $this->assertContains('created_at', $colNames);
    }

    public function testMigrateAssignsStepIncrementally(): void
    {
        // 1. Crear dos migraciones iniciales para el primer lote (Batch / Step 1)
        $c1 = "m000001_{$this->testRunId}_alpha";
        $c2 = "m000002_{$this->testRunId}_beta";
        $this->createDummyMigration($c1, "alpha_{$this->testRunId}");
        $this->createDummyMigration($c2, "beta_{$this->testRunId}");

        $migrator = new Migrator($this->pdo, $this->tempDir, silent: true);
        $appliedBatch1 = $migrator->migrate();

        $this->assertCount(2, $appliedBatch1);
        $this->assertSame(1, $migrator->getCurrentStep());

        $history1 = $migrator->getMigrationHistory();
        $this->assertCount(2, $history1);
        $this->assertSame(1, (int)$history1[0]['step']);
        $this->assertSame(1, (int)$history1[1]['step']);

        // 2. Crear una tercera migración para un segundo lote (Batch / Step 2)
        $c3 = "m000003_{$this->testRunId}_gamma";
        $this->createDummyMigration($c3, "gamma_{$this->testRunId}");
        $appliedBatch2 = $migrator->migrate();

        $this->assertCount(1, $appliedBatch2);
        $this->assertSame(2, $migrator->getCurrentStep());

        $history2 = $migrator->getMigrationHistory();
        $this->assertCount(3, $history2);
        $this->assertSame(2, (int)$history2[2]['step']);
    }

    public function testRollbackOnlyRevertsLastStepByDefault(): void
    {
        $c1 = "m000001_{$this->testRunId}_alpha";
        $c2 = "m000002_{$this->testRunId}_beta";
        $c3 = "m000003_{$this->testRunId}_gamma";

        // Paso 1: Migrar lote 1 (dos migraciones)
        $this->createDummyMigration($c1, "alpha_{$this->testRunId}");
        $this->createDummyMigration($c2, "beta_{$this->testRunId}");

        $migrator = new Migrator($this->pdo, $this->tempDir, silent: true);
        $migrator->migrate();

        // Paso 2: Migrar lote 2 (una migración)
        $this->createDummyMigration($c3, "gamma_{$this->testRunId}");
        $migrator->migrate();

        $this->assertCount(3, $migrator->getAppliedMigrations());

        // Paso 3: Ejecutar rollback() por defecto (revierte únicamente el último step: Step 2)
        // Conserva Step 1 intacto sin borrar toda la base de datos
        $rolledBack = $migrator->rollback();

        $this->assertCount(1, $rolledBack);
        $this->assertSame("{$c3}.php", $rolledBack[0]);

        // Verificar que las migraciones de Step 1 siguen en la base de datos
        $remaining = $migrator->getAppliedMigrations();
        $this->assertCount(2, $remaining);
        $this->assertContains("{$c1}.php", $remaining);
        $this->assertContains("{$c2}.php", $remaining);
        $this->assertSame(1, $migrator->getCurrentStep());

        // Verificar que la tabla gamma fue destruida pero alpha y beta siguen existiendo
        $tables = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
        $this->assertContains("alpha_{$this->testRunId}", $tables);
        $this->assertContains("beta_{$this->testRunId}", $tables);
        $this->assertNotContains("gamma_{$this->testRunId}", $tables);
    }

    public function testRollbackAllRevertsEverything(): void
    {
        $c1 = "m000001_{$this->testRunId}_alpha";
        $c2 = "m000002_{$this->testRunId}_beta";
        $c3 = "m000003_{$this->testRunId}_gamma";

        $this->createDummyMigration($c1, "alpha_{$this->testRunId}");
        $this->createDummyMigration($c2, "beta_{$this->testRunId}");

        $migrator = new Migrator($this->pdo, $this->tempDir, silent: true);
        $migrator->migrate();

        $this->createDummyMigration($c3, "gamma_{$this->testRunId}");
        $migrator->migrate();

        // Revertir todo con null
        $rolledBack = $migrator->rollback(null);

        $this->assertCount(3, $rolledBack);
        $this->assertEmpty($migrator->getAppliedMigrations());
        $this->assertSame(0, $migrator->getCurrentStep());
    }

    private function createDummyMigration(string $className, string $tableName): void
    {
        $content = "<?php\n\nnamespace App\Infrastructure\Database\Migrations;\n\nuse PDO;\n\nif (!class_exists('App\\Infrastructure\\Database\\Migrations\\{$className}')) {\n    class {$className}\n    {\n        public function up(PDO \$db): void\n        {\n            \$db->exec(\"CREATE TABLE IF NOT EXISTS {$tableName} (id INTEGER PRIMARY KEY);\");\n        }\n\n        public function down(PDO \$db): void\n        {\n            \$db->exec(\"DROP TABLE IF EXISTS {$tableName};\");\n        }\n    }\n}\n";

        file_put_contents($this->tempDir . '/' . $className . '.php', $content);
    }
}
