<?php

namespace App\Application\Vehicles\UseCases;

use App\Domain\Auth\Exceptions\DocumentExtractionException;
use App\Domain\Auth\Services\DocumentExtractorInterface;
use App\Domain\Vehicles\Services\VehicleMatcherService;
use Exception;

class ScanVehicleRegistration
{
    public function __construct(
        private ?DocumentExtractorInterface $documentExtractor,
        private VehicleMatcherService $matcherService
    ) {}

    /**
     * @param string $filePath Ruta local del carnet de circulación o título escaneado
     * @param string|null $documentType 'vehicle_registration' o 'vehicle_title' / 'niv_certificate'
     * @return array Datos sugeridos y normalizados para el formulario del frontend
     */
    public function execute(string $filePath, ?string $documentType = null): array
    {
        if (!file_exists($filePath)) {
            throw new Exception("El archivo del documento vehicular no fue encontrado.", 400);
        }

        $rawOcr = [];
        if ($this->documentExtractor !== null) {
            try {
                // Si el extractor soporta carnet de circulación / título o extracción genérica
                if (method_exists($this->documentExtractor, 'extractVehicleRegistration')) {
                    $rawOcr = $this->documentExtractor->extractVehicleRegistration($filePath, $documentType);
                }
            } catch (DocumentExtractionException $e) {
                // Si el OCR no puede leer, se permite devolver array vacío para llenado manual
                $rawOcr = [];
            }
        }

        // Hacer coincidir contra el catálogo maestro de la base de datos
        $suggested = $this->matcherService->match($rawOcr);

        return [
            'suggested_vehicle' => $suggested,
            'document_type'     => $rawOcr['document_type'] ?? ($documentType ?: 'vehicle_registration'),
            'warnings'          => $rawOcr['warnings'] ?? [],
            'raw_ocr'           => $rawOcr,
            'file_path'         => $filePath,
            'message'           => 'Documento escaneado. Por favor verifique los campos sugeridos antes de confirmar.'
        ];
    }
}
