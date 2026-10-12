<?php

namespace App\Infrastructure\Database;

use App\Infrastructure\Database\Database;

class Migrator {
    private Database $db;

    public function __construct(Database $db) {
        $this->db = $db;
    }

    public function migrate() {
        $this->createMigrationTable();
        $appliedMigrations = $this->getAppliedMigrations();
        $newMigrations = [];
        $files = scandir(__DIR__ . '/Migrations');

        $toApply = array_diff($files, $appliedMigrations);

        foreach ($toApply as $migration) {
            if ($migration === '.' || $migration === '..') continue;

            require_once __DIR__ . '/Migrations/' . $migration;
            $className = pathinfo($migration, PATHINFO_FILENAME);
            $class = "App\\Infrastructure\\Database\\Migrations\\" . $className;
            (new $class($this->db))->up($this->db->getConnection());
            echo "Migrated: {$migration}\n";
            $newMigrations[] = $migration;
        }

        if (!empty($newMigrations)) {
            $this->saveMigrations($newMigrations);
        } else {
            echo "All migrations are applied.\n";
        }
    }

    public function rollback() {
        $this->createMigrationTable();
        $appliedMigrations = $this->getAppliedMigrations();
        $newMigrations = [];
        $files = scandir(__DIR__ . '/Migrations');
        $files = array_reverse($files);

        $toRollback = array_intersect($files, $appliedMigrations);

        foreach ($toRollback as $migration) {
            if ($migration === '.' || $migration === '..') continue;

            require_once __DIR__ . '/Migrations/' . $migration;
            $className = pathinfo($migration, PATHINFO_FILENAME);
            $class = "App\\Infrastructure\\Database\\Migrations\\" . $className;
            (new $class($this->db))->down($this->db->getConnection());
            echo "Rolled back: {$migration}\n";
            $newMigrations[] = $migration;
        }

        if (!empty($newMigrations)) {
            $this->deleteMigrations($newMigrations);
        } else {
            echo "No migrations to rollback.\n";
        }
    }

    private function createMigrationTable() {
        $this->db->getConnection()->exec("
            CREATE TABLE IF NOT EXISTS migrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=INNODB;
        ");
    }

    private function getAppliedMigrations() {
        $statement = $this->db->getConnection()->prepare("SELECT migration FROM migrations");
        $statement->execute();
        return $statement->fetchAll(\PDO::FETCH_COLUMN);
    }

    private function saveMigrations(array $migrations) {
        $str = implode(",", array_map(fn($m) => "('$m')", $migrations));
        $this->db->getConnection()->exec("INSERT INTO migrations (migration) VALUES {$str}");
    }

    private function deleteMigrations(array $migrations) {
        $str = implode(",", array_map(fn($m) => "'$m'", $migrations));
        $this->db->getConnection()->exec("DELETE FROM migrations WHERE migration IN ({$str})");
    }
}