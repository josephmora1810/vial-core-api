<?php

namespace App\Controllers;

use App\Application\Towing\UseCases\AcceptSosService;
use App\Application\Towing\UseCases\ArriveTowService;
use App\Application\Towing\UseCases\CancelTowService;
use App\Application\Towing\UseCases\CompleteTowService;
use App\Application\Towing\UseCases\FindNearbyPendingServices;
use App\Application\Towing\UseCases\GetTowService;
use App\Application\Towing\UseCases\RateTowService;
use App\Application\Towing\UseCases\RequestSosAssistance;
use App\Application\Towing\UseCases\UpdateCraneTelemetry;
use App\Core\Request;
use App\Core\Response;
use App\Domain\Shared\ValueObjects\Coordinates;
use App\Domain\Users\Entities\User;
use DomainException;
use Exception;
use InvalidArgumentException;

class TowingController
{
    public function __construct(
        private RequestSosAssistance $requestSosAssistance,
        private AcceptSosService $acceptSosService,
        private UpdateCraneTelemetry $updateCraneTelemetry,
        private ArriveTowService $arriveTowService,
        private CompleteTowService $completeTowService,
        private CancelTowService $cancelTowService,
        private RateTowService $rateTowService,
        private GetTowService $getTowService,
        private FindNearbyPendingServices $findNearbyPendingServices
    ) {}

    /**
     * POST /api/v1/sos
     * Solicita auxilio vial de emergencia (FR-20, FR-22, FR-23-B).
     */
    public function requestSos(Request $request): void
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($user === null || $user->getId() === null) {
            Response::json([], 401, 'fail', 'No autorizado.');
            return;
        }

        $vehicleId = $request->input('vehicle_id');
        $originLat = $request->input('origin_lat');
        $originLng = $request->input('origin_lng');
        $originAddress = $request->input('origin_address');
        $destLat = $request->input('dest_lat');
        $destLng = $request->input('dest_lng');
        $destAddress = $request->input('dest_address');
        $failureDesc = $request->input('failure_description');
        $savedAddressId = $request->input('saved_address_id');

        $errors = [];
        if (empty($vehicleId)) {
            $errors['vehicle_id'] = 'El vehículo averiado es obligatorio.';
        }
        if ($originLat === null || $originLng === null || !is_numeric($originLat) || !is_numeric($originLng)) {
            $errors['origin'] = 'Las coordenadas de origen (origin_lat, origin_lng) son obligatorias y numéricas.';
        }
        if (empty($originAddress)) {
            $errors['origin_address'] = 'La dirección de origen es obligatoria.';
        }
        if ($destLat === null || $destLng === null || !is_numeric($destLat) || !is_numeric($destLng)) {
            $errors['destination'] = 'Las coordenadas de destino (dest_lat, dest_lng) son obligatorias y numéricas.';
        }
        if (empty($destAddress)) {
            $errors['dest_address'] = 'La dirección de destino es obligatoria.';
        }
        if (empty($failureDesc)) {
            $errors['failure_description'] = 'La descripción de la falla es obligatoria.';
        }

        if (!empty($errors)) {
            Response::json($errors, 422, 'fail', 'Datos incompletos o inválidos.');
            return;
        }

        try {
            $origin = new Coordinates((float)$originLat, (float)$originLng);
            $destination = new Coordinates((float)$destLat, (float)$destLng);

            $result = $this->requestSosAssistance->execute(
                clientId: (int)$user->getId(),
                vehicleIdentifier: $vehicleId,
                origin: $origin,
                originAddress: (string)$originAddress,
                destination: $destination,
                destinationAddress: (string)$destAddress,
                failureDescription: (string)$failureDesc,
                savedAddressIdentifier: $savedAddressId
            );

            Response::json($result, 201, 'success', 'Solicitud de auxilio vial (SOS) registrada exitosamente.');
        } catch (DomainException $e) {
            $code = (is_int($e->getCode()) && $e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 422;
            Response::json([], $code, 'fail', $e->getMessage());
        } catch (Exception $e) {
            $code = (is_int($e->getCode()) && $e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 400;
            Response::json([], $code, 'fail', $e->getMessage());
        }
    }

    /**
     * GET /api/v1/sos/radar (o GET /api/v1/radar)
     * Radar geoespacial de solicitudes SOS en estado PENDING para conductores de grúa (FR-23).
     */
    public function radar(Request $request): void
    {
        $lat = $request->input('lat');
        $lng = $request->input('lng');
        $radiusKm = $request->input('radius_km', 25.0);

        if ($lat === null || $lng === null || !is_numeric($lat) || !is_numeric($lng)) {
            Response::json(['coordinates' => 'Las coordenadas del chofer (lat, lng) son obligatorias y numéricas.'], 422, 'fail', 'Faltan parámetros de geolocalización.');
            return;
        }

        try {
            $driverLoc = new Coordinates((float)$lat, (float)$lng);
            $services = $this->findNearbyPendingServices->execute($driverLoc, (float)$radiusKm);

            Response::json(['services' => $services], 200, 'success', 'Radar de solicitudes cercanas consultado exitosamente.');
        } catch (Exception $e) {
            $code = (is_int($e->getCode()) && $e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 400;
            Response::json([], $code, 'fail', $e->getMessage());
        }
    }

    /**
     * GET /api/v1/sos/{uuid}
     * Consulta el detalle y estado actual de un servicio de asistencia vial.
     */
    public function show(Request $request, string $uuid): void
    {
        try {
            $service = $this->getTowService->execute($uuid, $request->user()?->getId());
            Response::json($service, 200, 'success', 'Detalle de servicio de asistencia consultado exitosamente.');
        } catch (Exception $e) {
            $code = (is_int($e->getCode()) && $e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 404;
            Response::json([], $code, 'fail', $e->getMessage());
        }
    }

    /**
     * POST /api/v1/sos/{uuid}/accept
     * Conductor adjudica un servicio de grúa en PENDING aplicando Pessimistic Lock (FR-24, NFR-11-B).
     */
    public function accept(Request $request, string $uuid): void
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($user === null || $user->getId() === null) {
            Response::json([], 401, 'fail', 'No autorizado.');
            return;
        }

        try {
            $service = $this->acceptSosService->execute($uuid, (int)$user->getId());
            Response::json($service, 200, 'success', 'Servicio de asistencia aceptado exitosamente. En ruta al destino.');
        } catch (DomainException $e) {
            $code = $e->getCode() === 409 ? 409 : 400;
            Response::json([], $code, 'fail', $e->getMessage());
        } catch (Exception $e) {
            $code = (is_int($e->getCode()) && $e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 400;
            Response::json([], $code, 'fail', $e->getMessage());
        }
    }

    /**
     * PUT|POST /api/v1/sos/{uuid}/telemetry
     * Emisión de pulsos GPS de telemetría de alta frecuencia por el gruero (FR-25).
     */
    public function telemetry(Request $request, string $uuid): void
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($user === null || $user->getId() === null) {
            Response::json([], 401, 'fail', 'No autorizado.');
            return;
        }

        $lat = $request->input('lat');
        $lng = $request->input('lng');
        $speed = $request->input('speed_kmh', 0.0);

        if ($lat === null || $lng === null || !is_numeric($lat) || !is_numeric($lng)) {
            Response::json(['coordinates' => 'Las coordenadas GPS actuales (lat, lng) son obligatorias.'], 422, 'fail', 'Datos incompletos.');
            return;
        }

        try {
            $coords = new Coordinates((float)$lat, (float)$lng);
            $log = $this->updateCraneTelemetry->execute($uuid, (int)$user->getId(), $coords, (float)$speed);

            Response::json($log, 200, 'success', 'Pulso de telemetría registrado exitosamente.');
        } catch (DomainException $e) {
            $code = (is_int($e->getCode()) && $e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 400;
            Response::json([], $code, 'fail', $e->getMessage());
        } catch (Exception $e) {
            $code = (is_int($e->getCode()) && $e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 400;
            Response::json([], $code, 'fail', $e->getMessage());
        }
    }

    /**
     * POST /api/v1/sos/{uuid}/arrive
     * El conductor notifica su llegada física al punto donde se ubica el cliente averiado.
     */
    public function arrive(Request $request, string $uuid): void
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($user === null || $user->getId() === null) {
            Response::json([], 401, 'fail', 'No autorizado.');
            return;
        }

        try {
            $service = $this->arriveTowService->execute($uuid, (int)$user->getId());
            Response::json($service, 200, 'success', 'Llegada de la grúa al punto de origen confirmada exitosamente.');
        } catch (DomainException $e) {
            $code = (is_int($e->getCode()) && $e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 400;
            Response::json([], $code, 'fail', $e->getMessage());
        } catch (Exception $e) {
            $code = (is_int($e->getCode()) && $e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 400;
            Response::json([], $code, 'fail', $e->getMessage());
        }
    }

    /**
     * POST /api/v1/sos/{uuid}/complete
     * El conductor culmina el servicio tras remolcar y entregar el vehículo en destino (FR-27).
     */
    public function complete(Request $request, string $uuid): void
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($user === null || $user->getId() === null) {
            Response::json([], 401, 'fail', 'No autorizado.');
            return;
        }

        try {
            $service = $this->completeTowService->execute($uuid, (int)$user->getId());
            Response::json($service, 200, 'success', 'Servicio de remolque completado exitosamente.');
        } catch (DomainException $e) {
            $code = (is_int($e->getCode()) && $e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 400;
            Response::json([], $code, 'fail', $e->getMessage());
        } catch (Exception $e) {
            $code = (is_int($e->getCode()) && $e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 400;
            Response::json([], $code, 'fail', $e->getMessage());
        }
    }

    /**
     * POST /api/v1/sos/{uuid}/cancel
     * Cancela un servicio de auxilio por parte del cliente o conductor (FR-24-B, FR-24-C).
     */
    public function cancel(Request $request, string $uuid): void
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($user === null || $user->getId() === null) {
            Response::json([], 401, 'fail', 'No autorizado.');
            return;
        }

        $reason = $request->input('reason');
        if (empty($reason)) {
            Response::json(['reason' => 'El motivo de cancelación es obligatorio.'], 422, 'fail', 'Datos incompletos.');
            return;
        }

        $actor = $user->hasRole('driver') ? 'driver' : 'client';

        try {
            $service = $this->cancelTowService->execute($uuid, (int)$user->getId(), (string)$reason, $actor);
            Response::json($service, 200, 'success', 'Servicio de asistencia vial cancelado exitosamente.');
        } catch (DomainException $e) {
            $code = (is_int($e->getCode()) && $e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 400;
            Response::json([], $code, 'fail', $e->getMessage());
        } catch (Exception $e) {
            $code = (is_int($e->getCode()) && $e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 400;
            Response::json([], $code, 'fail', $e->getMessage());
        }
    }

    /**
     * POST /api/v1/sos/{uuid}/rate
     * Califica el servicio culminado con 1 a 5 estrellas (FR-27).
     */
    public function rate(Request $request, string $uuid): void
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($user === null || $user->getId() === null) {
            Response::json([], 401, 'fail', 'No autorizado.');
            return;
        }

        $rating = $request->input('rating');
        $comment = $request->input('comment');

        if ($rating === null || !is_numeric($rating) || (int)$rating < 1 || (int)$rating > 5) {
            Response::json(['rating' => 'La calificación debe ser un número entero entre 1 y 5 estrellas.'], 422, 'fail', 'Datos inválidos.');
            return;
        }

        try {
            $result = $this->rateTowService->execute(
                serviceIdentifier: $uuid,
                userId: (int)$user->getId(),
                stars: (int)$rating,
                comment: $comment !== null ? (string)$comment : null
            );

            Response::json($result, 201, 'success', 'Calificación registrada exitosamente.');
        } catch (DomainException $e) {
            $code = (is_int($e->getCode()) && $e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 400;
            Response::json([], $code, 'fail', $e->getMessage());
        } catch (Exception $e) {
            $code = (is_int($e->getCode()) && $e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 400;
            Response::json([], $code, 'fail', $e->getMessage());
        }
    }
}
