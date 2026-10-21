<?php

namespace App\Domain\Vehicles\Services;

use App\Domain\Vehicles\Repositories\VehicleCatalogRepositoryInterface;

/**
 * Servicio de Dominio para normalizar y hacer coincidir los datos leídos por el OCR
 * del Carnet de Circulación contra el catálogo maestro de la base de datos (FR-18).
 */
class VehicleMatcherService
{
    public function __construct(
        private VehicleCatalogRepositoryInterface $catalogRepository
    ) {}

    /**
     * @param array{
     *     plate_number?: ?string,
     *     niv?: ?string,
     *     engine_serial?: ?string,
     *     brand?: ?string,
     *     model?: ?string,
     *     year?: ?int,
     *     color?: ?string,
     *     vehicle_class?: ?string
     * } $rawOcrData
     * @return array
     */
    public function match(array $rawOcrData): array
    {
        $rawBrand = $rawOcrData['brand'] ?? null;
        $rawModel = $rawOcrData['model'] ?? null;

        $matchedBrand = null;
        $matchedModel = null;
        $inferredClassId = null;

        // 1. Intentar hacer coincidir la marca con el catálogo maestro
        if (!empty($rawBrand)) {
            $matchedBrand = $this->catalogRepository->findBrandByName($rawBrand);
        }

        // 2. Si se encontró la marca, intentar hacer coincidir el modelo
        if ($matchedBrand !== null && !empty($rawModel)) {
            $matchedModel = $this->catalogRepository->findModelByNameAndBrand(
                $rawModel,
                (int)$matchedBrand['id']
            );

            if ($matchedModel !== null && !empty($matchedModel['vehicle_class_id'])) {
                $inferredClassId = (int)$matchedModel['vehicle_class_id'];
            }
        }

        return [
            'plate_number'       => !empty($rawOcrData['plate_number']) ? strtoupper(trim($rawOcrData['plate_number'])) : null,
            'niv'                => !empty($rawOcrData['niv']) ? strtoupper(trim($rawOcrData['niv'])) : null,
            'engine_serial'      => !empty($rawOcrData['engine_serial']) ? strtoupper(trim($rawOcrData['engine_serial'])) : null,
            'year'               => !empty($rawOcrData['year']) ? (int)$rawOcrData['year'] : null,
            'color'              => !empty($rawOcrData['color']) ? ucwords(strtolower(trim($rawOcrData['color']))) : null,
            'brand_id'           => $matchedBrand ? (int)$matchedBrand['id'] : null,
            'brand_name'         => $matchedBrand ? $matchedBrand['name'] : ($rawBrand ? trim($rawBrand) : null),
            'model_id'           => $matchedModel ? (int)$matchedModel['id'] : null,
            'model_name'         => $matchedModel ? $matchedModel['name'] : ($rawModel ? trim($rawModel) : null),
            'vehicle_class_id'   => $inferredClassId,
            'is_matched'         => $matchedBrand !== null,
        ];
    }
}
