<?php

namespace App\Controllers;

use App\Application\Driver\UseCases\EndDriverShift;
use App\Application\Driver\UseCases\GetDriverFleetStatus;
use App\Application\Driver\UseCases\RegisterDriverCrane;
use App\Application\Driver\UseCases\StartDriverShift;
use App\Application\Driver\UseCases\UpdateDriverLocation;
use App\Application\Driver\UseCases\UploadDriverLicense;
use App\Application\Vehicles\DTOs\VehicleResponseDto;
use App\Core\Request;
use App\Core\Response;
use App\Domain\Driver\Repositories\DriverShiftRepositoryInterface;
use App\Domain\Shared\ValueObjects\Coordinates;
use App\Domain\Users\Entities\User;
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
        private ?DriverShiftRepositoryInterface $driverShiftRepository = null
    ) {}

    /**
     * POST /api/v1/driver/cranes
     * Registra una nueva unidad de grúa (CRANE) con su Certificado de Registro / Carnet INTT (FR-09).
     */
    public function registerCrane(Request $request): void
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($user === null || $user->getId() === null) {
            Response::json([], 401, 'fail', 'No autorizado.');
            return;
        }

        // Manejo de archivo multipart para el Carnet de la grúa
        $carnetFilePath = null;
        if (!empty($_FILES['carnet']['tmp_name'])) {
            $uploaded = $_FILES['carnet'];
            $ext = pathinfo($uploaded['name'], PATHINFO_EXTENSION);
            $uploadsDir = dirname(__DIR__, 2) . '/storage/documents';
            if (!is_dir($uploadsDir)) {
                mkdir($uploadsDir, 0755, true);
            }
            $targetName = sprintf('crane_carnet_%d_%s.%s', $user->getId(), bin2hex(random_bytes(6)), $ext ?: 'jpg');
            $targetPath = $uploadsDir . '/' . $targetName;
            $moved = is_uploaded_file($uploaded['tmp_name'])
                ? move_uploaded_file($uploaded['tmp_name'], $targetPath)
                : copy($uploaded['tmp_name'], $targetPath);
            if ($moved) {
                $carnetFilePath = $targetPath;
            }
        } elseif (!empty($request->input('carnet_file_path'))) {
            $inputPath = (string)$request->input('carnet_file_path');
            $real = realpath($inputPath);
            $storage = realpath(dirname(__DIR__, 2) . '/storage');
            $temp = realpath(sys_get_temp_dir());
            if ($real !== false && (($storage && str_starts_with($real, $storage)) || ($temp && str_starts_with($real, $temp)))) {
                $carnetFilePath = $real;
            }
        }

        // Manejo de archivo multipart opcional para Licencia de Conducir
        $licenseFilePath = null;
        if (!empty($_FILES['license']['tmp_name'])) {
            $uploadedLic = $_FILES['license'];
            $ext = pathinfo($uploadedLic['name'], PATHINFO_EXTENSION);
            $uploadsDir = dirname(__DIR__, 2) . '/storage/documents';
            if (!is_dir($uploadsDir)) {
                mkdir($uploadsDir, 0755, true);
            }
            $targetName = sprintf('driver_lic_%d_%s.%s', $user->getId(), bin2hex(random_bytes(6)), $ext ?: 'jpg');
            $targetPath = $uploadsDir . '/' . $targetName;
            $moved = is_uploaded_file($uploadedLic['tmp_name'])
                ? move_uploaded_file($uploadedLic['tmp_name'], $targetPath)
                : copy($uploadedLic['tmp_name'], $targetPath);
            if ($moved) {
                $licenseFilePath = $targetPath;
            }
        } elseif (!empty($request->input('license_file_path'))) {
            $inputPath = (string)$request->input('license_file_path');
            $real = realpath($inputPath);
            $storage = realpath(dirname(__DIR__, 2) . '/storage');
            $temp = realpath(sys_get_temp_dir());
            if ($real !== false && (($storage && str_starts_with($real, $storage)) || ($temp && str_starts_with($real, $temp)))) {
                $licenseFilePath = $real;
            }
        }

        try {
            $result = $this->registerDriverCrane->execute(
                driverUserId: $user->getId(),
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

            Response::json($result, 201, 'success', 'Grúa registrada con éxito en el sistema.');
        } catch (Exception $e) {
            $code = $e->getCode();
            $httpCode = (is_int($code) && $code >= 400 && $code < 600) ? $code : 400;
            Response::json([], $httpCode, 'fail', $e->getMessage());
        }
    }

    /**
     * POST /api/v1/driver/license
     * Carga o actualiza la Licencia de Conducir del Chofer/Gruero (FR-09).
     */
    public function uploadLicense(Request $request): void
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($user === null || $user->getId() === null) {
            Response::json([], 401, 'fail', 'No autorizado.');
            return;
        }

        $licenseFilePath = null;
        if (!empty($_FILES['license']['tmp_name'])) {
            $uploaded = $_FILES['license'];
            $ext = pathinfo($uploaded['name'], PATHINFO_EXTENSION);
            $uploadsDir = dirname(__DIR__, 2) . '/storage/documents';
            if (!is_dir($uploadsDir)) {
                mkdir($uploadsDir, 0755, true);
            }
            $targetName = sprintf('driver_lic_%d_%s.%s', $user->getId(), bin2hex(random_bytes(6)), $ext ?: 'jpg');
            $targetPath = $uploadsDir . '/' . $targetName;
            $moved = is_uploaded_file($uploaded['tmp_name'])
                ? move_uploaded_file($uploaded['tmp_name'], $targetPath)
                : copy($uploaded['tmp_name'], $targetPath);
            if ($moved) {
                $licenseFilePath = $targetPath;
            }
        } elseif (!empty($request->input('file_path'))) {
            $inputPath = (string)$request->input('file_path');
            $real = realpath($inputPath);
            $storage = realpath(dirname(__DIR__, 2) . '/storage');
            $temp = realpath(sys_get_temp_dir());
            if ($real !== false && (($storage && str_starts_with($real, $storage)) || ($temp && str_starts_with($real, $temp)))) {
                $licenseFilePath = $real;
            }
        }

        if (empty($licenseFilePath)) {
            Response::json(['license' => 'Debe adjuntar la imagen de la Licencia de Conducir.'], 422, 'fail', 'Archivo requerido.');
            return;
        }

        try {
            $result = $this->uploadDriverLicense->execute(
                driverUserId: $user->getId(),
                filePath: $licenseFilePath,
                grade: $request->input('grade', '5TO GRADO'),
                expirationDate: $request->input('expiration_date')
            );

            Response::json($result, 201, 'success', 'Licencia de conducir cargada exitosamente.');
        } catch (Exception $e) {
            $code = $e->getCode();
            $httpCode = (is_int($code) && $code >= 400 && $code < 600) ? $code : 400;
            Response::json([], $httpCode, 'fail', $e->getMessage());
        }
    }

    /**
     * GET /api/v1/driver/status
     * Retorna el estado integral de la flota y documentación del Gruero (FR-09, FR-10).
     */
    public function status(Request $request): void
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($user === null || $user->getId() === null) {
            Response::json([], 401, 'fail', 'No autorizado.');
            return;
        }

        try {
            $status = $this->getDriverFleetStatus->execute($user->getId());
            Response::json($status, 200, 'success', 'Estado de flota y documentación consultado con éxito.');
        } catch (Exception $e) {
            $code = $e->getCode();
            $httpCode = (is_int($code) && $code >= 400 && $code < 600) ? $code : 400;
            Response::json([], $httpCode, 'fail', $e->getMessage());
        }
    }

    /**
     * GET /api/v1/driver/cranes
     * Retorna el listado de grúas registradas para el operador.
     */
    public function listCranes(Request $request): void
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($user === null || $user->getId() === null) {
            Response::json([], 401, 'fail', 'No autorizado.');
            return;
        }

        if ($this->vehicleRepository === null) {
            Response::json([], 500, 'error', 'Repositorio de vehículos no configurado.');
            return;
        }

        $cranes = $this->vehicleRepository->findCranesByUserId($user->getId());
        $dtos = array_map(fn($c) => VehicleResponseDto::fromEntity($c)->toArray(), $cranes);

        Response::json($dtos, 200, 'success', 'Grúas del operador consultadas con éxito.');
    }

    /**
     * POST /api/v1/driver/shift/start
     * Inicia turno de trabajo y activa una grúa de la flota.
     */
    public function startShift(Request $request): void
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($user === null || $user->getId() === null) {
            Response::json([], 401, 'fail', 'No autorizado.');
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
                driverUserId: (int)$user->getId(),
                craneIdentifier: $craneId,
                initialLatitude: $latVal,
                initialLongitude: $lngVal
            );

            Response::json($result, 200, 'success', $result['message']);
        } catch (DomainException $e) {
            $code = $e->getCode();
            $httpCode = (is_int($code) && $code >= 400 && $code < 600) ? $code : 422;
            Response::json([], $httpCode, 'fail', $e->getMessage());
        } catch (Exception $e) {
            $code = $e->getCode();
            $httpCode = (is_int($code) && $code >= 400 && $code < 600) ? $code : 500;
            Response::json([], $httpCode, 'error', $e->getMessage());
        }
    }

    /**
     * POST /api/v1/driver/cranes/{id}/activate
     * Activa una grúa específica para el turno del chofer (desactivando cualquier otra unidad de su flota).
     */
    public function activateCrane(Request $request, array $params = []): void
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($user === null || $user->getId() === null) {
            Response::json([], 401, 'fail', 'No autorizado.');
            return;
        }

        if ($this->startDriverShift === null) {
            Response::json([], 500, 'error', 'Servicio de activación de grúas no configurado.');
            return;
        }

        $craneId = $params['id'] ?? $request->input('crane_id');
        if (empty($craneId)) {
            Response::json(['crane_id' => 'Identificador de la grúa requerido.'], 422, 'fail', 'Parámetro faltante.');
            return;
        }

        try {
            $result = $this->startDriverShift->execute(
                driverUserId: (int)$user->getId(),
                craneIdentifier: $craneId
            );

            Response::json($result, 200, 'success', 'Grúa activada como unidad de guardia para el chofer.');
        } catch (DomainException $e) {
            $code = $e->getCode();
            $httpCode = (is_int($code) && $code >= 400 && $code < 600) ? $code : 409;
            Response::json([], $httpCode, 'fail', $e->getMessage());
        } catch (Exception $e) {
            $code = $e->getCode();
            $httpCode = (is_int($code) && $code >= 400 && $code < 600) ? $code : 500;
            Response::json([], $httpCode, 'error', $e->getMessage());
        }
    }

    /**
     * POST /api/v1/driver/shift/end
     * Finaliza el turno del chofer y lo desactiva del radar de emergencias.
     */
    public function endShift(Request $request): void
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($user === null || $user->getId() === null) {
            Response::json([], 401, 'fail', 'No autorizado.');
            return;
        }

        if ($this->endDriverShift === null) {
            Response::json([], 500, 'error', 'Servicio de turnos no configurado.');
            return;
        }

        try {
            $result = $this->endDriverShift->execute((int)$user->getId());
            Response::json($result, 200, 'success', $result['message']);
        } catch (DomainException $e) {
            $code = $e->getCode();
            $httpCode = (is_int($code) && $code >= 400 && $code < 600) ? $code : 409;
            Response::json([], $httpCode, 'fail', $e->getMessage());
        } catch (Exception $e) {
            $code = $e->getCode();
            $httpCode = (is_int($code) && $code >= 400 && $code < 600) ? $code : 500;
            Response::json([], $httpCode, 'error', $e->getMessage());
        }
    }

    /**
     * POST /api/v1/driver/location
     * Emite latido de ubicación en segundo plano para el radar geoespacial (NFR-14-B).
     */
    public function updateLocation(Request $request): void
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($user === null || $user->getId() === null) {
            Response::json([], 401, 'fail', 'No autorizado.');
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
                driverUserId: (int)$user->getId(),
                coordinates: $coords,
                speedKmh: $speed
            );

            Response::json($result, 200, 'success', 'Ubicación de radar actualizada con éxito.');
        } catch (DomainException $e) {
            $code = $e->getCode();
            $httpCode = (is_int($code) && $code >= 400 && $code < 600) ? $code : 400;
            Response::json([], $httpCode, 'fail', $e->getMessage());
        } catch (Exception $e) {
            $code = $e->getCode();
            $httpCode = (is_int($code) && $code >= 400 && $code < 600) ? $code : 500;
            Response::json([], $httpCode, 'error', $e->getMessage());
        }
    }
}
