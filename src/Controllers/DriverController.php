<?php

namespace App\Controllers;

use App\Application\Driver\UseCases\GetDriverFleetStatus;
use App\Application\Driver\UseCases\RegisterDriverCrane;
use App\Application\Driver\UseCases\UploadDriverLicense;
use App\Application\Vehicles\DTOs\VehicleResponseDto;
use App\Core\Request;
use App\Core\Response;
use App\Domain\Users\Entities\User;
use App\Domain\Vehicles\Repositories\VehicleRepositoryInterface;
use Exception;

class DriverController
{
    public function __construct(
        private RegisterDriverCrane $registerDriverCrane,
        private UploadDriverLicense $uploadDriverLicense,
        private GetDriverFleetStatus $getDriverFleetStatus,
        private ?VehicleRepositoryInterface $vehicleRepository = null
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
            if (move_uploaded_file($uploaded['tmp_name'], $targetPath)) {
                $carnetFilePath = $targetPath;
            }
        } elseif (!empty($request->input('carnet_file_path'))) {
            $carnetFilePath = (string)$request->input('carnet_file_path');
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
            if (move_uploaded_file($uploadedLic['tmp_name'], $targetPath)) {
                $licenseFilePath = $targetPath;
            }
        } elseif (!empty($request->input('license_file_path'))) {
            $licenseFilePath = (string)$request->input('license_file_path');
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
            if (move_uploaded_file($uploaded['tmp_name'], $targetPath)) {
                $licenseFilePath = $targetPath;
            }
        } elseif (!empty($request->input('file_path'))) {
            $licenseFilePath = (string)$request->input('file_path');
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
}
