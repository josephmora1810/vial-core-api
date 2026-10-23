<?php

namespace App\Infrastructure\Services\Towing;

use App\Domain\Shared\ValueObjects\Coordinates;
use App\Domain\Towing\Service\RoutingServiceInterface;
use App\Domain\Towing\ValueObjects\RouteSummary;
use App\Infrastructure\Http\HttpClientInterface;
use Throwable;

/**
 * Summary of OsrmRoutingService
 */
class OsrmRoutingService implements RoutingServiceInterface
{
    private string $baseUrl;
    public function __construct(
        private HttpClientInterface $httpClient,
        ?string $baseUrl = null
    ) {
        $this->baseUrl = rtrim($baseUrl ?? ($_ENV['OSRM_BASE_URL'] ?? 'https://router.project-osrm.org'), '/');
    }

    public function calculateRoute(Coordinates $origin, Coordinates $destination): RouteSummary
    {
        $originLng = $origin->getLongitude();
        $originLat = $origin->getLatitude();
        $destLng = $destination->getLongitude();
        $destLat = $destination->getLatitude();
        // Endpoint OSRM: /route/v1/driving/{lng1},{lat1};{lng2},{lat2}?overview=false
        $url = sprintf(
            '%s/route/v1/driving/%.6f,%.6f;%.6f,%.6f',
            $this->baseUrl,
            $originLng,
            $originLat,
            $destLng,
            $destLat
        );
        try {
            $response = $this->httpClient->get($url, ['overview' => 'false']);
            if ($response->isSuccessful()) {
                $data = $response->json();
                if (($data['code'] ?? '') === 'Ok' && !empty($data['routes'][0])) {
                    $route = $data['routes'][0];
                    $distanceMeters = (float)($route['distance'] ?? 0.0);
                    $durationSeconds = (float)($route['duration'] ?? 0.0);
                    return new RouteSummary(
                        distanceKm: round($distanceMeters / 1000.0, 2),
                        durationMinutes: round($durationSeconds / 60.0, 1),
                        geometryPolyline: $route['geometry'] ?? null
                    );
                }
            }
        } catch (Throwable) {
            // Si OSRM no responde o falla la red, se activa el fallback
        }
        // Fallback matemático resiliente (Haversine + factor de curvatura vial)
        return $this->fallbackHaversine($origin, $destination);
    }

    private function fallbackHaversine(Coordinates $origin, Coordinates $destination): RouteSummary
    {
        $earthRadiusKm = 6371.0;
        $lat1 = deg2rad($origin->getLatitude());
        $lon1 = deg2rad($origin->getLongitude());
        $lat2 = deg2rad($destination->getLatitude());
        $lon2 = deg2rad($destination->getLongitude());
        $dLat = $lat2 - $lat1;
        $dLon = $lon2 - $lon1;
        $a = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * (sin($dLon / 2) ** 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $straightDistanceKm = $earthRadiusKm * $c;
        $roadDistanceKm = round($straightDistanceKm * 1.30, 2);
        $durationMinutes = round(($roadDistanceKm / 35.0) * 60.0, 1);
        return new RouteSummary(
            distanceKm: max(1.0, $roadDistanceKm),
            durationMinutes: max(5.0, $durationMinutes),
            geometryPolyline: null
        );
    }
}