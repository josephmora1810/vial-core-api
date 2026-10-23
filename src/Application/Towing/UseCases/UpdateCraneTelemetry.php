<?php

namespace App\Application\Towing\UseCases;

use App\Domain\Shared\Services\BroadcasterInterface;
use App\Domain\Shared\ValueObjects\Coordinates;
use App\Domain\Shared\ValueObjects\Uuid;
use App\Domain\Towing\Entities\TowServiceLog;
use App\Domain\Towing\Enums\TowServiceStatus;
use App\Domain\Towing\Repositories\TowServiceLogRepositoryInterface;
use App\Domain\Towing\Repositories\TowServiceRepositoryInterface;
use DomainException;
use Exception;

class UpdateCraneTelemetry
{
    public function __construct(
        private TowServiceRepositoryInterface $towServiceRepository,
        private TowServiceLogRepositoryInterface $logRepository,
        private ?BroadcasterInterface $broadcaster = null
    ) {}

    /**
     * Registra un pulso GPS de telemetría de alta frecuencia emitido por la grúa (FR-25).
     *
     * @param string|int $serviceIdentifier ID o UUID del servicio
     * @param int $driverId ID del conductor autenticado
     * @param Coordinates $coordinates Coordenadas actuales del gruero
     * @param float $speedKmh Velocidad reportada en km/h
     * @return array<string, mixed>
     */
    public function execute(
        string|int $serviceIdentifier,
        int $driverId,
        Coordinates $coordinates,
        float $speedKmh = 0.0
    ): array {
        $service = is_numeric($serviceIdentifier)
            ? $this->towServiceRepository->findById((int)$serviceIdentifier)
            : $this->towServiceRepository->findByUuid(Uuid::fromString((string)$serviceIdentifier));

        if ($service === null) {
            throw new Exception('El servicio de auxilio solicitado no existe.', 404);
        }

        if ($service->getDriverId() !== $driverId) {
            throw new DomainException('No estás asignado como conductor de este servicio.', 403);
        }

        $status = $service->getStatus();
        if ($status !== TowServiceStatus::InRoute && $status !== TowServiceStatus::InProgress) {
            throw new DomainException('El servicio no se encuentra en tránsito (IN_ROUTE o IN_PROGRESS).', 400);
        }

        $log = TowServiceLog::record(
            towServiceId: (int)$service->getId(),
            coordinates: $coordinates,
            speedKmh: max(0.0, round($speedKmh, 2))
        );

        $this->logRepository->append($log);

        $result = [
            'status'      => 'logged',
            'coordinates' => [
                'latitude'  => $coordinates->getLatitude(),
                'longitude' => $coordinates->getLongitude(),
            ],
            'speed_kmh'   => $log->getSpeedKmh(),
            'recorded_at' => $log->getCreatedAt()?->format('Y-m-d H:i:s'),
        ];

        // Difusión en vivo por WebSockets al canal privado del cliente (FR-26)
        $this->broadcaster?->broadcast(
            channels: "private-tow-{$service->getUuid()->getValue()}",
            event: 'location-updated',
            data: $result
        );

        return $result;
    }
}
