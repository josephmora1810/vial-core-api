<?php

namespace App\Infrastructure\Database;

use PDO;

class SeederRunner
{
    public function __construct(private Database $db) {}

    public function run(?string $specificSeeder = null): void
    {
        $this->createSeederTable();
        $appliedSeeders = $this->getAppliedSeeders();
        $files = scandir(__DIR__ . '/Seeders');
        sort($files);

        $newSeeders = [];

        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || !str_ends_with($file, '.php')) {
                continue;
            }

            if ($specificSeeder !== null && $file !== $specificSeeder && !str_starts_with($file, $specificSeeder)) {
                continue;
            }

            if (in_array($file, $appliedSeeders, true)) {
                echo "Already seeded: {$file}\n";
                continue;
            }

            require_once __DIR__ . '/Seeders/' . $file;
            $className = pathinfo($file, PATHINFO_FILENAME);
            $fullClass = "App\\Infrastructure\\Database\\Seeders\\" . $className;

            if (!class_exists($fullClass)) {
                echo "Warning: Class {$fullClass} not found in {$file}\n";
                continue;
            }

            $seederInstance = new $fullClass();
            $seederInstance->run($this->db->getConnection());
            echo "Seeded: {$file}\n";

            $this->saveSeeder($file);
            $newSeeders[] = $file;
        }

        if (empty($newSeeders)) {
            echo "All seeders are up to date.\n";
        }
    }

    private function createSeederTable(): void
    {
        $this->db->getConnection()->exec("
            CREATE TABLE IF NOT EXISTS seeders (
                id INT AUTO_INCREMENT PRIMARY KEY,
                seeder VARCHAR(255) NOT NULL UNIQUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }

    private function getAppliedSeeders(): array
    {
        $stmt = $this->db->getConnection()->prepare("SELECT seeder FROM seeders");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function saveSeeder(string $seeder): void
    {
        $stmt = $this->db->getConnection()->prepare("INSERT INTO seeders (seeder) VALUES (:seeder) ON DUPLICATE KEY UPDATE seeder = seeder");
        $stmt->execute(['seeder' => $seeder]);
    }
}
