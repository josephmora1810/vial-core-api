<?php

namespace App\Infrastructure\Database;

use PDO;

class Migrator {
    private Database|PDO $db;
    private string $migrationsPath;
    private bool $silent;

    public function __construct(
        Database|PDO $db,
        ?string $migrationsPath = null,
        bool $silent = false
    ) {
        $this->db = $db;
        $this->migrationsPath = $migrationsPath ?? (__DIR__ . '/Migrations');
        $this->silent = $silent;
    }

    /**
     * Aplica las migraciones pendientes asignándoles un nuevo número de step.
     *
     * @return list<string> Lista de nombres de archivos de migraciones aplicadas
     */
    public function migrate(): array {
        $this->createMigrationTable();
        $pdo = $this->getPdo();
        $appliedMigrations = $this->getAppliedMigrations();
        $newMigrations = [];

        $files = scandir($this->migrationsPath);
        if ($files === false) {
            $files = [];
        }
        sort($files);

        $toApply = array_diff($files, $appliedMigrations);
        $step = $this->getNextStep();

        foreach ($toApply as $migration) {
            if ($migration === '.' || $migration === '..') continue;
            if (!str_ends_with($migration, '.php')) continue;

            require_once $this->migrationsPath . '/' . $migration;
            $className = pathinfo($migration, PATHINFO_FILENAME);
            $class = "App\\Infrastructure\\Database\\Migrations\\" . $className;
            if (class_exists($class)) {
                $instance = new $class($this->db);
                if (method_exists($instance, 'up')) {
                    $instance->up($pdo);
                }
            }
            $this->log("Migrated: {$migration} (step {$step})\n");
            $newMigrations[] = $migration;
        }

        if (!empty($newMigrations)) {
            $this->saveMigrations($newMigrations, $step);
            $this->log("Applied " . count($newMigrations) . " migration(s) in step {$step}.\n");
        } else {
            $this->log("All migrations are applied.\n");
        }

        return $newMigrations;
    }

    /**
     * Revierte migraciones aplicadas.
     * Por defecto revierte únicamente el último step ejecutado para evitar borrar toda la base de datos.
     *
     * @param int|null $steps Cantidad de steps hacia atrás a revertir (null = revierte todas)
     * @return list<string> Lista de migraciones revertidas
     */
    public function rollback(?int $steps = 1): array {
        $this->createMigrationTable();
        $pdo = $this->getPdo();

        $toRollback = $this->getMigrationsForRollback($steps);

        if (empty($toRollback)) {
            $this->log("No migrations to rollback.\n");
            return [];
        }

        $rolledBack = [];
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver !== 'sqlite') {
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
        }

        try {
            foreach ($toRollback as $migration) {
                if ($migration === '.' || $migration === '..') continue;

                $file = $this->migrationsPath . '/' . $migration;
                if (!file_exists($file)) {
                    $this->log("Warning: Migration file {$migration} not found, skipping down() execution.\n");
                    $rolledBack[] = $migration;
                    continue;
                }

                require_once $file;
                $className = pathinfo($migration, PATHINFO_FILENAME);
                $class = "App\\Infrastructure\\Database\\Migrations\\" . $className;
                if (class_exists($class)) {
                    $instance = new $class($this->db);
                    if (method_exists($instance, 'down')) {
                        $instance->down($pdo);
                    }
                }
                $this->log("Rolled back: {$migration}\n");
                $rolledBack[] = $migration;
            }
        } finally {
            if ($driver !== 'sqlite') {
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
            }
        }

        if (!empty($rolledBack)) {
            $this->deleteMigrations($rolledBack);
            $this->log("Rolled back " . count($rolledBack) . " migration(s).\n");
        }

        return $rolledBack;
    }

    /**
     * Retorna la lista de migraciones a revertir según los steps solicitados.
     *
     * @return list<string>
     */
    public function getMigrationsForRollback(?int $steps = 1): array {
        $pdo = $this->getPdo();

        if ($steps === null) {
            // Revertir todas las migraciones aplicadas en orden inverso
            $stmt = $pdo->query("SELECT migration FROM migrations ORDER BY id DESC");
            return $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
        }

        $steps = max(1, $steps);

        // Obtener los últimos N steps existentes
        $stmt = $pdo->prepare("SELECT DISTINCT step FROM migrations ORDER BY step DESC LIMIT :limit");
        $stmt->bindValue(':limit', $steps, PDO::PARAM_INT);
        $stmt->execute();
        $targetSteps = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($targetSteps)) {
            return [];
        }

        // Obtener las migraciones que pertenecen a esos steps en orden inverso
        $placeholders = implode(',', array_fill(0, count($targetSteps), '?'));
        $stmt = $pdo->prepare("SELECT migration FROM migrations WHERE step IN ({$placeholders}) ORDER BY id DESC");
        $stmt->execute($targetSteps);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * @return list<string>
     */
    public function getAppliedMigrations(): array {
        $statement = $this->getPdo()->prepare("SELECT migration FROM migrations ORDER BY id ASC");
        $statement->execute();
        return $statement->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Retorna el número de step actual más alto registrado en la tabla.
     */
    public function getCurrentStep(): int {
        try {
            $stmt = $this->getPdo()->query("SELECT MAX(step) FROM migrations");
            $maxStep = $stmt ? $stmt->fetchColumn() : null;
            return $maxStep !== false && $maxStep !== null ? (int)$maxStep : 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Retorna el número del siguiente step a asignar en la próxima ejecución de migrate().
     */
    public function getNextStep(): int {
        return $this->getCurrentStep() + 1;
    }

    /**
     * Retorna el historial completo de migraciones aplicadas con su step y fecha.
     *
     * @return list<array{id: int, migration: string, step: int, created_at: string}>
     */
    public function getMigrationHistory(): array {
        try {
            $stmt = $this->getPdo()->query("SELECT id, migration, step, created_at FROM migrations ORDER BY id ASC");
            return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (\Throwable) {
            return [];
        }
    }

    public function createMigrationTable(): void {
        $pdo = $this->getPdo();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS migrations (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    migration VARCHAR(255) NOT NULL,
                    step INTEGER NOT NULL DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                );
            ");
        } else {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS migrations (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    migration VARCHAR(255) NOT NULL,
                    step INT NOT NULL DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=INNODB;
            ");

            // Si la tabla migrations ya existía previamente sin la columna 'step', la agregamos automáticamente
            try {
                $stmt = $pdo->query("SHOW COLUMNS FROM migrations LIKE 'step'");
                if ($stmt && !$stmt->fetch()) {
                    $pdo->exec("ALTER TABLE migrations ADD COLUMN step INT NOT NULL DEFAULT 1 AFTER migration");
                }
            } catch (\Throwable) {
                // Silencioso si ya existe o no se soporta SHOW COLUMNS
            }
        }
    }

    private function log(string $message): void {
        if (!$this->silent) {
            echo $message;
        }
    }

    private function saveMigrations(array $migrations, int $step): void {
        if (empty($migrations)) return;
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare("INSERT INTO migrations (migration, step) VALUES (?, ?)");
        foreach ($migrations as $migration) {
            $stmt->execute([$migration, $step]);
        }
    }

    private function deleteMigrations(array $migrations): void {
        if (empty($migrations)) return;
        $pdo = $this->getPdo();
        $placeholders = implode(',', array_fill(0, count($migrations), '?'));
        $stmt = $pdo->prepare("DELETE FROM migrations WHERE migration IN ({$placeholders})");
        $stmt->execute($migrations);
    }

    private function getPdo(): PDO {
        return $this->db instanceof PDO ? $this->db : $this->db->getConnection();
    }
}
