<?php

namespace App\Controllers;

use App\Application\Driver\DTOs\{
    DriverCraneRegistrationResponseDto,
    DriverFleetStatusResponseDto,
    DriverLicenseResponseDto,
    DriverLocationResponseDto,
    DriverShiftActionResponseDto
};
use App\Application\Driver\UseCases\{
    EndDriverShift,
    GetCurrentDriverShift,
    GetDriverFleetStatus,
    RegisterDriverCrane,
    StartDriverShift,
    UpdateDriverLocation,
    UploadDriverLicense
};
use App\Application\Vehicles\DTOs\VehicleResponseDto;
use App\Core\{Request, Response};
use App\Domain\Driver\Repositories\DriverShiftRepositoryInterface;
use App\Domain\Shared\ValueObjects\Coordinates;
use App\Domain\Vehicles\Repositories\VehicleRepositoryInterface;
use DomainException;
use Exception;

class DriverController
{
    public function __construct(
        private RegisterDriverCrane $registerDriverCrane,
        private UploadDriverLicense $uploadDriverLicense,
        private GetDriverFleetStatus $getDriverFleetStatus,
        private ?VehicleRepositoryInterface $vehicleRepository = null,
        private ?StartDriverShift $startDriverShift = null,
        private ?EndDriverShift $endDriverShift = null,
        private ?UpdateDriverLocation $updateDriverLocation = null,
        private ?DriverShiftRepositoryInterface $driverShiftRepository = null,
        private ?GetCurrentDriverShift $getCurrentDriverShift = null
    ) {}

    /**
     * POST /api/v1/driver/cranes
     * Registra una nueva unidad de grúa (CRANE) con su Certificado de Registro / Carnet INTT (FR-09).
     */
    public function registerCrane(Request $request): void
    {
        $userId = $this->authenticatedUserId($request);
        if ($userId === null) {
            $this->unauthorized();
            return;
        }

        // Archivo del Carnet de la grúa (multipart o ruta pre-existente confiable)
        $carnetFilePath = $this->resolveUploadedFile($request, 'carnet', 'carnet_file_path', 'crane_carnet', $userId);

        // Archivo opcional de la Licencia de Conducir
        $licenseFilePath = $this->resolveUploadedFile($request, 'license', 'license_file_path', 'driver_lic', $userId);

        try {
            $result = $this->registerDriverCrane->execute(
                driverUserId: $userId,
                rawPlate: $request->input('plate_number'),
                rawNiv: $request->input('niv'),
                rawYear: $request->input('year') !== null ? (int)$request->input('year') : null,
                rawColor: $request->input('color'),
                rawEngineSerial: $request->input('engine_serial'),
                rawBrand: $request->input('brand'),
                rawModel: $request->input('model'),
                brandId: $request->input('brand_id') !== null ? (int)$request->input('brand_id') : null,
                modelId: $request->input('model_id') !== null ? (int)$request->input('model_id') : null,
                carnetFilePath: $carnetFilePath,
                carnetDocType: $request->input('document_type', 'vehicle_registration'),
                licenseFilePath: $licenseFilePath,
                licenseGrade: $request->input('license_grade', '5TO GRADO')
            );

            $dto = DriverCraneRegistrationResponseDto::fromArray($result);
            Response::json($dto->toArray(), 201, 'success', 'Grúa registrada con éxito en el sistema.');
        } catch (Exception $e) {
            $this->fail($e, 400);
        }
    }

    /**
     * POST /api/v1/driver/license
     * Carga o actualiza la Licencia de Conducir del Chofer/Gruero (FR-09).
     */
    public function uploadLicense(Request $request): void
    {
        $userId = $this->authenticatedUserId($request);
        if ($userId === null) {
            $this->unauthorized();
            return;
        }

        $licenseFilePath = $this->resolveUploadedFile($request, 'license', 'file_path', 'driver_lic', $userId);

        if (empty($licenseFilePath)) {
            Response::json(['license' => 'Debe adjuntar la imagen de la Licencia de Conducir.'], 422, 'fail', 'Archivo requerido.');
            return;
        }

        try {
            $result = $this->uploadDriverLicense->execute(
                driverUserId: $userId,
                filePath: $licenseFilePath,
                grade: $request->input('grade', '5TO GRADO'),
                expirationDate: $request->input('expiration_date')
            );

            $dto = DriverLicenseResponseDto::fromArray($result);
            Response::json($dto->toArray(), 201, 'success', 'Licencia de conducir cargada exitosamente.');
        } catch (Exception $e) {
            $this->fail($e, 400);
        }
    }

    /**
     * GET /api/v1/driver/status
     * Retorna el estado integral de la flota y documentación del Gruero (FR-09, FR-10).
     */
    public function status(Request $request): void
    {
        $userId = $this->authenticatedUserId($request);
        if ($userId === null) {
            $this->unauthorized();
            return;
        }

        try {
            $status = $this->getDriverFleetStatus->execute($userId);
            $dto = DriverFleetStatusResponseDto::fromArray($status);
            Response::json($dto->toArray(), 200, 'success', 'Estado de flota y documentación consultado con éxito.');
        } catch (Exception $e) {
            $this->fail($e, 400);
        }
    }

    /**
     * GET /api/v1/driver/cranes
     * Retorna el listado de grúas registradas para el operador.
     */
    public function listCranes(Request $request): void
    {
        $userId = $this->authenticatedUserId($request);
        if ($userId === null) {
            $this->unauthorized();
            return;
        }

        if ($this->vehicleRepository === null) {
            Response::json([], 500, 'error', 'Repositorio de vehículos no configurado.');
            return;
        }

        $cranes = $this->vehicleRepository->findCranesByUserId($userId);
        $dtos = array_map(fn($c) => VehicleResponseDto::fromEntity($c)->toArray(), $cranes);

        Response::json($dtos, 200, 'success', 'Grúas del operador consultadas con éxito.');
    }

    /**
     * GET /api/v1/driver/shift/current
     * Retorna el turno activo del chofer (o un estado inactivo si no tiene turno en curso).
     */
    public function currentShift(Request $request): void
    {
        $userId = $this->authenticatedUserId($request);
        if ($userId === null) {
            $this->unauthorized();
            return;
        }

        if ($this->getCurrentDriverShift === null) {
            Response::json([], 500, 'error', 'Servicio de turnos no configurado.');
            return;
        }

        try {
            $dto = $this->getCurrentDriverShift->execute($userId);
            Response::json($dto->toArray(), 200, 'success', 'Turno actual consultado con éxito.');
        } catch (Exception $e) {
            $this->fail($e, 500, 'error');
        }
    }

    /**
     * POST /api/v1/driver/shift/start
     * Inicia turno de trabajo y activa una grúa de la flota.
     */
    public function startShift(Request $request): void
    {
        $userId = $this->authenticatedUserId($request);
        if ($userId === null) {
            $this->unauthorized();
            return;
        }

        if ($this->startDriverShift === null) {
            Response::json([], 500, 'error', 'Servicio de turnos no configurado.');
            return;
        }

        $craneId = $request->input('crane_id');
        $lat = $request->input('latitude');
        $lng = $request->input('longitude');

        if (empty($craneId)) {
            Response::json(['crane_id' => 'Debe seleccionar la grúa que operará durante este turno.'], 422, 'fail', 'Datos incompletos.');
            return;
        }

        try {
            $latVal = ($lat !== null && is_numeric($lat)) ? (float)$lat : null;
            $lngVal = ($lng !== null && is_numeric($lng)) ? (float)$lng : null;

            $result = $this->startDriverShift->execute(
                driverUserId: $userId,
                craneIdentifier: $craneId,
                initialLatitude: $latVal,
                initialLongitude: $lngVal
            );

            $dto = DriverShiftActionResponseDto::fromArray($result);
            Response::json($dto->toArray(), 200, 'success', $dto->message);
        } catch (DomainException $e) {
            $this->fail($e, 422);
        } catch (Exception $e) {
            $this->fail($e, 500, 'error');
        }
    }

    /**
     * POST /api/v1/driver/cranes/{id}/activate
     * Activa una grúa específica para el turno del chofer (desactivando cualquier otra unidad de su flota).
     *
     * @param array<string, mixed> $params
     */
    public function activateCrane(Request $request, string|array|null $id = null): void
    {
        $userId = $this->authenticatedUserId($request);
        if ($userId === null) {
            $this->unauthorized();
            return;
        }

        if ($this->startDriverShift === null) {
            Response::json([], 500, 'error', 'Servicio de activación de grúas no configurado.');
            return;
        }

        $craneId = is_array($id) ? ($id['id'] ?? null) : ($id ?: $request->input('crane_id'));
        if (empty($craneId)) {
            Response::json(['crane_id' => 'Identificador de la grúa requerido.'], 422, 'fail', 'Parámetro faltante.');
            return;
        }

        try {
            $result = $this->startDriverShift->execute(
                driverUserId: $userId,
                craneIdentifier: $craneId
            );

            $dto = DriverShiftActionResponseDto::fromArray($result);
            Response::json($dto->toArray(), 200, 'success', 'Grúa activada como unidad de guardia para el chofer.');
        } catch (DomainException $e) {
            $this->fail($e, 409);
        } catch (Exception $e) {
            $this->fail($e, 500, 'error');
        }
    }

    /**
     * POST /api/v1/driver/shift/end
     * Finaliza el turno del chofer y lo desactiva del radar de emergencias.
     */
    public function endShift(Request $request): void
    {
        $userId = $this->authenticatedUserId($request);
        if ($userId === null) {
            $this->unauthorized();
            return;
        }

        if ($this->endDriverShift === null) {
            Response::json([], 500, 'error', 'Servicio de turnos no configurado.');
            return;
        }

        try {
            $result = $this->endDriverShift->execute($userId);
            $dto = DriverShiftActionResponseDto::fromArray($result);
            Response::json($dto->toArray(), 200, 'success', $dto->message);
        } catch (DomainException $e) {
            $this->fail($e, 409);
        } catch (Exception $e) {
            $this->fail($e, 500, 'error');
        }
    }

    /**
     * POST /api/v1/driver/location
     * Emite latido de ubicación en segundo plano para el radar geoespacial (NFR-14-B).
     */
    public function updateLocation(Request $request): void
    {
        $userId = $this->authenticatedUserId($request);
        if ($userId === null) {
            $this->unauthorized();
            return;
        }

        if ($this->updateDriverLocation === null) {
            Response::json([], 500, 'error', 'Servicio de telemetría de radar no configurado.');
            return;
        }

        $lat = $request->input('latitude') ?? $request->input('lat');
        $lng = $request->input('longitude') ?? $request->input('lng');
        $speed = (float)($request->input('speed_kmh') ?? 0.0);

        if ($lat === null || $lng === null || !is_numeric($lat) || !is_numeric($lng)) {
            Response::json(['coordinates' => 'Latitud y longitud numéricas son obligatorias.'], 422, 'fail', 'Coordenadas inválidas.');
            return;
        }

        try {
            $coords = new Coordinates((float)$lat, (float)$lng);
            $result = $this->updateDriverLocation->execute(
                driverUserId: $userId,
                coordinates: $coords,
                speedKmh: $speed
            );

            $dto = DriverLocationResponseDto::fromArray($result);
            Response::json($dto->toArray(), 200, 'success', 'Ubicación de radar actualizada con éxito.');
        } catch (DomainException $e) {
            $this->fail($e, 400);
        } catch (Exception $e) {
            $this->fail($e, 500, 'error');
        }
    }

    /**
     * Retorna el ID del usuario autenticado o null si la petición no está autenticada.
     */
    private function authenticatedUserId(Request $request): ?int
    {
        $user = $request->user();
        if ($user === null || $user->getId() === null) {
            return null;
        }

        return (int)$user->getId();
    }

    private function unauthorized(): void
    {
        Response::json([], 401, 'fail', 'No autorizado.');
    }

    /**
     * Emite una respuesta de error usando el código de la excepción si es un HTTP 4xx/5xx válido;
     * en caso contrario utiliza el código por defecto indicado.
     */
    private function fail(Exception $e, int $defaultCode, string $status = 'fail'): void
    {
        $code = $e->getCode();
        $httpCode = (is_int($code) && $code >= 400 && $code < 600) ? $code : $defaultCode;
        Response::json([], $httpCode, $status, $e->getMessage());
    }

    /**
     * Resuelve la ruta local de un archivo recibido por la petición:
     *  1. Archivo multipart ($_FILES[$fileKey]): se guarda en storage/documents con nombre aleatorio.
     *  2. Ruta pre-existente ($pathInputKey): solo se acepta si reside dentro de /storage o del directorio temporal.
     *
     * @return string|null Ruta absoluta del archivo o null si no hay archivo válido.
     */
    private function resolveUploadedFile(
        Request $request,
        string $fileKey,
        string $pathInputKey,
        string $filePrefix,
        int $userId
    ): ?string {
        if (!empty($_FILES[$fileKey]['tmp_name'])) {
            $uploaded = $_FILES[$fileKey];
            $ext = pathinfo((string)$uploaded['name'], PATHINFO_EXTENSION);
            $uploadsDir = dirname(__DIR__, 2) . '/storage/documents';
            if (!is_dir($uploadsDir)) {
                mkdir($uploadsDir, 0755, true);
            }
            $targetName = sprintf('%s_%d_%s.%s', $filePrefix, $userId, bin2hex(random_bytes(6)), $ext ?: 'jpg');
            $targetPath = $uploadsDir . '/' . $targetName;
            $moved = is_uploaded_file($uploaded['tmp_name'])
                ? move_uploaded_file($uploaded['tmp_name'], $targetPath)
                : copy($uploaded['tmp_name'], $targetPath);

            return $moved ? $targetPath : null;
        }

        if (!empty($request->input($pathInputKey))) {
            $real = realpath((string)$request->input($pathInputKey));
            $storage = realpath(dirname(__DIR__, 2) . '/storage');
            $temp = realpath(sys_get_temp_dir());
            if ($real !== false && (($storage && str_starts_with($real, $storage)) || ($temp && str_starts_with($real, $temp)))) {
                return $real;
            }
        }

        return null;
    }
}
