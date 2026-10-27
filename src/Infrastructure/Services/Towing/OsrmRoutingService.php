<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Towing;

use App\Domain\Shared\ValueObjects\Coordinates;
use App\Domain\Towing\{
    Service\RoutingServiceInterface,
    ValueObjects\RouteSummary
};
use App\Infrastructure\Http\HttpClientInterface;
use Throwable;

/**
 * Summary of OsrmRoutingService
 */
class OsrmRoutingService implements RoutingServiceInterface
{
    private string $baseUrl;
    private string $profile;

    public function __construct(
        private HttpClientInterface $httpClient,
        ?string $baseUrl = null,
        ?string $profile = null
    ) {
        $this->baseUrl = rtrim($baseUrl ?? ($_ENV['OSRM_BASE_URL'] ?? 'https://router.project-osrm.org'), '/');
        $this->profile = trim($profile ?? ($_ENV['OSRM_PROFILE'] ?? 'driving'), '/') ?: 'driving';
    }

    public function calculateRoute(Coordinates $origin, Coordinates $destination): RouteSummary
    {
        $originLng = $origin->getLongitude();
        $originLat = $origin->getLatitude();
        $destLng = $destination->getLongitude();
        $destLat = $destination->getLatitude();
        // Endpoint OSRM: /route/v1/{profile}/{lng1},{lat1};{lng2},{lat2}?overview=false
        $url = "{$this->baseUrl}/route/v1/{$this->profile}/{$originLng},{$originLat};{$destLng},{$destLat}?overview=false";

        try {
            $response = $this->httpClient->get($url);

            if ($response->isSuccessful() && is_array($response->data)) {
                $code = $response->data['code'] ?? null;
                if ($code === 'Ok' && !empty($response->data['routes'])) {
                    $firstRoute = $response->data['routes'][0];
                    $distanceMeters = (float)($firstRoute['distance'] ?? 0.0);
                    $durationSeconds = (float)($firstRoute['duration'] ?? 0.0);

                    $distanceKm = round($distanceMeters / 1000.0, 2);
                    $durationMinutes = round($durationSeconds / 60.0, 2);

                    return new RouteSummary(
                        distanceKm: $distanceKm,
                        durationMinutes: $durationMinutes
                    );
                }
            }
        } catch (Throwable) {
            // Fallback a Haversine si el servidor OSRM no responde o falla
        }

        return $this->fallbackHaversine($origin, $destination);
    }

    /**
     * Fallback matemático utilizando la fórmula de Haversine con factor de corrección urbano (1.3x).
     */
    private function fallbackHaversine(Coordinates $origin, Coordinates $destination): RouteSummary
    {
        $straightLineDistance = $origin->distanceTo($destination);
        // Factor de curvatura urbana y vial venezolana (aprox. 30% adicional a la línea recta)
        $roadDistanceKm = round($straightLineDistance * 1.30, 2);
        // Estimación a velocidad promedio urbana de 35 km/h
        $durationMinutes = round(($roadDistanceKm / 35.0) * 60.0, 2);

        return new RouteSummary(
            distanceKm: $roadDistanceKm,
            durationMinutes: $durationMinutes
        );
    }
}
