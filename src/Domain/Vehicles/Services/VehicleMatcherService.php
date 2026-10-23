<?php

namespace App\Domain\Vehicles\Services;

use App\Domain\Vehicles\Repositories\VehicleCatalogRepositoryInterface;

/**
 * Servicio de Dominio para normalizar y hacer coincidir los datos leídos por el OCR
 * del Carnet de Circulación o Título/NIV contra el catálogo maestro oficial (FR-18).
 * 
 * Principio de Calidad: NUNCA auto-crea marcas ni modelos "en caliente" ante lecturas
 * dudosas del OCR (ej. 'Berr' por 'Bera'). Si no hay coincidencia exacta o por subcadena,
 * marca `is_matched = false` y `requires_manual_selection = true` para que el usuario
 * seleccione los datos correctos mediante los desplegables de catálogo de la app.
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
        $inferredSubclassId = null;
        $inferredType = null;
        $warnings = [];

        // 1. Intentar hacer coincidir la marca con el catálogo maestro (612 marcas)
        if (!empty($rawBrand)) {
            $matchedBrand = $this->catalogRepository->findBrandByName($rawBrand);
        }

        // 2. Si se encontró la marca oficial, intentar hacer coincidir el modelo (5.413 modelos)
        if ($matchedBrand !== null && !empty($rawModel)) {
            $matchedModel = $this->catalogRepository->findModelByNameAndBrand(
                $rawModel,
                (int)$matchedBrand['id']
            );

            if ($matchedModel !== null) {
                if (!empty($matchedModel['vehicle_class_id'])) {
                    $inferredClassId = (int)$matchedModel['vehicle_class_id'];
                }
                if (!empty($matchedModel['vehicle_subclass_id'])) {
                    $inferredSubclassId = (int)$matchedModel['vehicle_subclass_id'];
                }
                if (!empty($matchedModel['type'])) {
                    $inferredType = (string)$matchedModel['type'];
                }
            } else {
                $warnings[] = "Marca '{$matchedBrand['name']}' reconocida, pero el modelo '{$rawModel}' requiere selección manual del catálogo.";
            }
        } elseif (!empty($rawBrand) && $matchedBrand === null) {
            $warnings[] = "La marca '{$rawBrand}' no fue reconocida en el catálogo. Por favor selecciónela manualmente.";
        }

        $isFullyMatched = ($matchedBrand !== null && $matchedModel !== null);

        return [
            'plate_number'              => !empty($rawOcrData['plate_number']) ? strtoupper(trim($rawOcrData['plate_number'])) : null,
            'niv'                       => !empty($rawOcrData['niv']) ? strtoupper(trim($rawOcrData['niv'])) : null,
            'engine_serial'             => !empty($rawOcrData['engine_serial']) ? strtoupper(trim($rawOcrData['engine_serial'])) : null,
            'year'                      => !empty($rawOcrData['year']) ? (int)$rawOcrData['year'] : null,
            'color'                     => !empty($rawOcrData['color']) ? ucwords(strtolower(trim($rawOcrData['color']))) : null,
            'brand_id'                  => $matchedBrand ? (int)$matchedBrand['id'] : null,
            'brand_name'                => $matchedBrand ? $matchedBrand['name'] : ($rawBrand ? trim($rawBrand) : null),
            'model_id'                  => $matchedModel ? (int)$matchedModel['id'] : null,
            'model_name'                => $matchedModel ? $matchedModel['name'] : ($rawModel ? trim($rawModel) : null),
            'vehicle_class_id'          => $inferredClassId,
            'vehicle_subclass_id'       => $inferredSubclassId,
            'type'                      => $inferredType,
            'is_matched'                => $isFullyMatched,
            'requires_manual_selection' => !$isFullyMatched,
            'raw_brand'                 => $rawBrand ? trim($rawBrand) : null,
            'raw_model'                 => $rawModel ? trim($rawModel) : null,
            'warnings'                  => $warnings,
        ];
    }
}
