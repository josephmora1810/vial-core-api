<?php

namespace App\Domain\Towing\Service;

use App\Domain\Shared\ValueObjects\Coordinates;
use App\Domain\Towing\ValueObjects\RouteSummary;

interface RoutingServiceInterface
{
    /**
     *  Calcula la ruta entre dos coordenadas y devuelve un resumen de la misma.
     * 
    */
    public function calculateRoute(Coordinates $origin, Coordinates $destination): RouteSummary;
}