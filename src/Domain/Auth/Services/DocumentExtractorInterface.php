<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\ValueObjects\ExtractedIdentity;

/**
 * Puerto de Dominio para extracción de documentos legales.
 * El dominio define la necesidad sin conocer la tecnología subyacente.
 */
interface DocumentExtractorInterface
{
    /**
     * Extrae y valida patronímicos de una fotografía de Cédula de Identidad.
     *
     * @param string $filePath Ruta local del archivo de imagen
     * @return ExtractedIdentity Datos patronímicos tipados y validados
     */
    public function extractIdentityCard(string $filePath): ExtractedIdentity;

    /**
     * Extrae datos del Carnet de Circulación vehicular o Título de Propiedad (NIV) vía OCR.
     *
     * @param string $filePath Ruta local del archivo de imagen
     * @param string|null $documentType 'vehicle_registration' (Carnet) o 'vehicle_title' (Título / NIV)
     * @return array
     */
    public function extractVehicleRegistration(string $filePath, ?string $documentType = null): array;
}
