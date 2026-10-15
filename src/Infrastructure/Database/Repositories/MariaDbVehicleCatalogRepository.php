<?php

namespace App\Infrastructure\Database\Repositories;

use App\Domain\Repositories\VehicleCatalogRepositoryInterface;
use PDO;

class MariaDbVehicleCatalogRepository implements VehicleCatalogRepositoryInterface
{
    public function __construct(private PDO $db) {}

    public function getClasses(): array
    {
        $stmt = $this->db->query("SELECT id, code, name, description FROM vehicle_classes ORDER BY name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSubclasses(?int $classId = null): array
    {
        if ($classId !== null) {
            $stmt = $this->db->prepare("SELECT id, vehicle_class_id, name, description FROM vehicle_subclasses WHERE vehicle_class_id = :class_id ORDER BY name ASC");
            $stmt->execute(['class_id' => $classId]);
        } else {
            $stmt = $this->db->query("SELECT id, vehicle_class_id, name, description FROM vehicle_subclasses ORDER BY name ASC");
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getBrands(?string $search = null): array
    {
        if (!empty($search)) {
            $stmt = $this->db->prepare("SELECT id, name FROM vehicle_brands WHERE name LIKE :search ORDER BY name ASC LIMIT 50");
            $stmt->execute(['search' => '%' . trim($search) . '%']);
        } else {
            $stmt = $this->db->query("SELECT id, name FROM vehicle_brands ORDER BY name ASC");
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findBrandByName(string $name): ?array
    {
        $cleanName = trim($name);
        $stmt = $this->db->prepare("SELECT id, name FROM vehicle_brands WHERE LOWER(name) = LOWER(:name) LIMIT 1");
        $stmt->execute(['name' => $cleanName]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            return $row;
        }

        // Búsqueda por subcadena si el OCR trae sufijo ("TOYOTA DE VENEZUELA" -> "TOYOTA")
        $stmtLike = $this->db->prepare("SELECT id, name FROM vehicle_brands WHERE :name LIKE CONCAT('%', name, '%') ORDER BY LENGTH(name) DESC LIMIT 1");
        $stmtLike->execute(['name' => $cleanName]);
        $likeRow = $stmtLike->fetch(PDO::FETCH_ASSOC);

        return $likeRow ?: null;
    }

    public function getModelsByBrand(int $brandId, ?string $search = null): array
    {
        if (!empty($search)) {
            $stmt = $this->db->prepare("
                SELECT id, brand_id, vehicle_class_id, name 
                FROM vehicle_models 
                WHERE brand_id = :brand_id AND name LIKE :search 
                ORDER BY name ASC
            ");
            $stmt->execute([
                'brand_id' => $brandId,
                'search'   => '%' . trim($search) . '%'
            ]);
        } else {
            $stmt = $this->db->prepare("
                SELECT id, brand_id, vehicle_class_id, name 
                FROM vehicle_models 
                WHERE brand_id = :brand_id 
                ORDER BY name ASC
            ");
            $stmt->execute(['brand_id' => $brandId]);
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findModelByNameAndBrand(string $modelName, int $brandId): ?array
    {
        $cleanModel = trim($modelName);
        $stmt = $this->db->prepare("
            SELECT id, brand_id, vehicle_class_id, name 
            FROM vehicle_models 
            WHERE brand_id = :brand_id AND LOWER(name) = LOWER(:name) 
            LIMIT 1
        ");
        $stmt->execute([
            'brand_id' => $brandId,
            'name'     => $cleanModel
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            return $row;
        }

        // Búsqueda por similitud/subcadena (ej: "COROLLA GLI" -> "COROLLA")
        $stmtLike = $this->db->prepare("
            SELECT id, brand_id, vehicle_class_id, name 
            FROM vehicle_models 
            WHERE brand_id = :brand_id AND :model LIKE CONCAT('%', name, '%') 
            ORDER BY LENGTH(name) DESC 
            LIMIT 1
        ");
        $stmtLike->execute([
            'brand_id' => $brandId,
            'model'    => $cleanModel
        ]);
        $likeRow = $stmtLike->fetch(PDO::FETCH_ASSOC);

        return $likeRow ?: null;
    }
}
