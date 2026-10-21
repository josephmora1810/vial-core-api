<?php

namespace App\Controllers;

use App\Application\Vehicles\UseCases\DeleteVehicle;
use App\Application\Vehicles\UseCases\ListUserVehicles;
use App\Application\Vehicles\UseCases\RegisterVehicle;
use App\Application\Vehicles\UseCases\ScanVehicleRegistration;
use App\Core\Request;
use App\Core\Response;
use App\Domain\Users\Entities\User;
use App\Domain\Vehicles\Repositories\VehicleCatalogRepositoryInterface;
use Exception;

class VehicleController
{
    public function __construct(
        private RegisterVehicle $registerVehicle,
        private ListUserVehicles $listUserVehicles,
        private DeleteVehicle $deleteVehicle,
        private ScanVehicleRegistration $scanVehicleRegistration,
        private VehicleCatalogRepositoryInterface $catalogRepository
    ) {}

    /**
     * GET /api/v1/vehicles
     * Lista todos los vehículos activos del usuario autenticado
     */
    public function index(Request $request): void
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($user === null || $user->getId() === null) {
            Response::json([], 401, 'fail', 'No autorizado.');
            return;
        }

        try {
            $vehicles = $this->listUserVehicles->execute($user->getId());
            Response::json($vehicles, 200, 'success', 'Vehículos consultados con éxito.');
        } catch (Exception $e) {
            Response::json([], 400, 'fail', $e->getMessage());
        }
    }

    /**
     * POST /api/v1/vehicles/scan
     * Procesa la imagen del Carnet de Circulación mediante OCR y sugiere datos normalizados
     */
    public function scan(Request $request): void
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($user === null) {
            Response::json([], 401, 'fail', 'No autorizado.');
            return;
        }

        $filePath = null;
        if (!empty($_FILES['document']['tmp_name'])) {
            $uploaded = $_FILES['document'];
            $ext = pathinfo($uploaded['name'], PATHINFO_EXTENSION);
            $uploadsDir = dirname(__DIR__, 2) . '/storage/documents';
            if (!is_dir($uploadsDir)) {
                mkdir($uploadsDir, 0755, true);
            }
            $targetPath = sprintf('%s/carnet_%d_%s.%s', $uploadsDir, $user->getId(), bin2hex(random_bytes(6)), $ext ?: 'jpg');
            if (move_uploaded_file($uploaded['tmp_name'], $targetPath)) {
                $filePath = $targetPath;
            }
        } elseif (!empty($request->input('file_path'))) {
            $filePath = (string)$request->input('file_path');
        }

        if (empty($filePath)) {
            $msg = $request->fileErrorMessage('document') ?? 'Debe adjuntar la foto o archivo del carnet de circulación o título.';
            Response::json(['document' => $msg], 422, 'fail', $msg);
            return;
        }

        $documentType = $request->input('document_type');

        try {
            $suggestion = $this->scanVehicleRegistration->execute($filePath, $documentType);
            Response::json($suggestion, 200, 'success', 'Documento vehicular procesado exitosamente.');
        } catch (Exception $e) {
            Response::json([], 400, 'fail', $e->getMessage());
        }
    }

    /**
     * POST /api/v1/vehicles
     * Registra y vincula un nuevo vehículo a la cuenta del usuario
     */
    public function store(Request $request): void
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($user === null || $user->getId() === null) {
            Response::json([], 401, 'fail', 'No autorizado.');
            return;
        }

        $plate = (string)$request->input('plate_number', '');
        $niv = (string)$request->input('niv', '');
        $year = (int)$request->input('year', 0);
        $color = (string)$request->input('color', '');

        if (empty($plate) || empty($niv) || $year <= 0 || empty($color)) {
            Response::json([
                'plate_number' => empty($plate) ? 'La placa es obligatoria.' : null,
                'niv'          => empty($niv) ? 'El N.I.V / Serial de carrocería es obligatorio.' : null,
                'year'         => $year <= 0 ? 'El año del vehículo es obligatorio.' : null,
                'color'        => empty($color) ? 'El color del vehículo es obligatorio.' : null,
            ], 422, 'fail', 'Datos de vehículo incompletos.');
            return;
        }

        try {
            $vehicle = $this->registerVehicle->execute(
                userId: $user->getId(),
                rawPlate: $plate,
                rawNiv: $niv,
                year: $year,
                color: $color,
                rawEngineSerial: $request->input('engine_serial') ? (string)$request->input('engine_serial') : null,
                brandId: $request->input('brand_id') !== null ? (int)$request->input('brand_id') : null,
                modelId: $request->input('model_id') !== null ? (int)$request->input('model_id') : null,
                vehicleClassId: $request->input('vehicle_class_id') !== null ? (int)$request->input('vehicle_class_id') : null,
                vehicleSubclassId: $request->input('vehicle_subclass_id') !== null ? (int)$request->input('vehicle_subclass_id') : null,
                brandName: $request->input('brand_name') ? (string)$request->input('brand_name') : null,
                modelName: $request->input('model_name') ? (string)$request->input('model_name') : null,
                carnetFilePath: $request->input('carnet_file_path') ? (string)$request->input('carnet_file_path') : null,
                documentType: $request->input('document_type') ? (string)$request->input('document_type') : null
            );

            Response::json($vehicle, 201, 'success', 'Vehículo registrado exitosamente.');
        } catch (Exception $e) {
            $code = $e->getCode();
            $httpCode = (is_int($code) && $code >= 400 && $code < 600) ? $code : 400;
            Response::json([], $httpCode, 'fail', $e->getMessage());
        }
    }

    /**
     * DELETE /api/v1/vehicles/{id}
     * Realiza soft delete de un vehículo por su UUID
     */
    public function destroy(Request $request, string $id): void
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($user === null || $user->getId() === null) {
            Response::json([], 401, 'fail', 'No autorizado.');
            return;
        }

        try {
            $this->deleteVehicle->execute($id, $user->getId());
            Response::json([], 200, 'success', 'Vehículo eliminado con éxito.');
        } catch (Exception $e) {
            $code = $e->getCode();
            $httpCode = (is_int($code) && $code >= 400 && $code < 600) ? $code : 400;
            Response::json([], $httpCode, 'fail', $e->getMessage());
        }
    }

    // --- Endpoints de Catálogo Maestro ---

    /**
     * GET /api/v1/vehicles/catalog/classes
     */
    public function classes(Request $request): void
    {
        $classes = $this->catalogRepository->getClasses();
        Response::json($classes, 200, 'success', 'Clases de vehículos consultadas.');
    }

    /**
     * GET /api/v1/vehicles/catalog/brands?q=toyota
     */
    public function brands(Request $request): void
    {
        $search = $request->input('q') ? (string)$request->input('q') : null;
        $brands = $this->catalogRepository->getBrands($search);
        Response::json($brands, 200, 'success', 'Marcas consultadas.');
    }

    /**
     * GET /api/v1/vehicles/catalog/models?brand_id=1&q=corolla
     */
    public function models(Request $request): void
    {
        $brandId = $request->input('brand_id');
        if ($brandId === null) {
            Response::json(['brand_id' => 'El parámetro brand_id es obligatorio.'], 422, 'fail', 'Parámetro faltante.');
            return;
        }

        $search = $request->input('q') ? (string)$request->input('q') : null;
        $models = $this->catalogRepository->getModelsByBrand((int)$brandId, $search);
        Response::json($models, 200, 'success', 'Modelos consultados.');
    }
}
