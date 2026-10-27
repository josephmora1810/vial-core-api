<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Towing;

use App\Domain\Shared\ValueObjects\Coordinates;
use App\Domain\Towing\{
    Repositories\CoverageZoneRepositoryInterface,
    ValueObjects\CoverageZone
};

class InMemoryCoverageZoneRepository implements CoverageZoneRepositoryInterface
{
    /** @var CoverageZone[] */
    private array $zones;

    public function __construct()
    {
        $this->zones = [
            new CoverageZone(
                code: 'CCS',
                name: 'Gran Caracas, Litoral Central y Eje Guarenas-Guatire',
                vertices: [
                    // --- Bloque Litoral Central (Norte) ---
                    new Coordinates(10.550000, -67.220000), // 1. Extremo Oeste La Guaira (Puerto Maya / límite Aragua)
                    new Coordinates(10.620000, -66.900000), // 2. Costa Central (Catia La Mar / Macuto / Caraballeda)
                    new Coordinates(10.610000, -66.320000), // 3. Extremo Este La Guaira (Chuspa / Caruao)

                    // --- Bloque Barlovento / Eje Guarenas-Guatire (Este) ---
                    new Coordinates(10.480000, -66.100000), // 4. Entrada a Higuerote (Garantiza Distribuidor Kempis)
                    new Coordinates(10.280000, -66.320000), // 5. Caucagua (Cubre el final de la Autopista GMA y Troncal 9)
                    new Coordinates(10.420000, -66.550000), // 6. Eje Guatire - Guarenas (Asegura toda la intercomunal)

                    // --- Bloque Valles del Tuy (Sureste / Sur) ---
                    new Coordinates(10.300000, -66.650000), // 7. Santa Lucía / Santa Teresa
                    new Coordinates(10.220000, -66.850000), // 8. Charallave / Cúa (Cubre la Autopista de los Valles del Tuy)

                    // --- Bloque Altos Mirandinos y Conexiones (Suroeste) ---
                    new Coordinates(10.330000, -67.040000), // 9. Los Teques / San Antonio (Cubre la Carretera Panamericana)
                    new Coordinates(10.300000, -67.180000), // 10. Límite Las Tejerías (Asegura el peaje de la ARC)

                    // --- Bloque Cierre de Anillo (Oeste) ---
                    new Coordinates(10.460000, -67.150000), // 11. El Junquito / Colonia Tovar (Carretera interna)
                    new Coordinates(10.510000, -67.220000), // 12. Carayaca (Cierre con el punto 1)
                ]
            ),
        ];
    }

    public function getActiveZones(): array
    {
        return $this->zones;
    }

    public function findMatchingZone(Coordinates $point): ?CoverageZone
    {
        foreach ($this->zones as $zone) {
            if ($zone->contains($point)) {
                return $zone;
            }
        }
        return null;
    }
}
