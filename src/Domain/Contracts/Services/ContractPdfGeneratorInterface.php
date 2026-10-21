<?php

namespace App\Domain\Contracts\Services;

use App\Domain\Contracts\Entities\Plan;
use App\Domain\Contracts\Entities\ServiceContract;
use App\Domain\Users\Entities\User;
use App\Domain\Vehicles\Entities\Vehicle;

/**
 * Puerto de Dominio para la generación del documento PDF del Contrato de Servicio (Plan de Grúa).
 */
interface ContractPdfGeneratorInterface
{
    /**
     * Genera el binario PDF (2 páginas: Carátula con recuadros/QR/marcas de agua + Cláusulas legales).
     *
     * @return string Contenido binario del archivo PDF (%PDF-1.x...)
     */
    public function generate(
        ServiceContract $contract,
        Plan $plan,
        User $client,
        ?Vehicle $vehicle = null,
        string $downloadUrl = '',
        ?string $clientAddress = null,
        ?string $vehicleClassLabel = null,
        string $branchName = 'PRINCIPAL - CARACAS',
        string $sellerName = 'CANAL DIGITAL UBIKGO'
    ): string;
}
