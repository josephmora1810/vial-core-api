<?php

namespace App\Controllers;

use App\Application\Contracts\UseCases\CheckoutServiceContract;
use App\Application\Contracts\UseCases\ListSellerContracts;
use App\Application\Contracts\UseCases\RenewServiceContract;
use App\Application\Users\UseCases\RegisterClientBySeller;
use App\Application\Users\UseCases\SearchClientByNationalId;
use App\Application\Vehicles\UseCases\RegisterVehicle;
use App\Core\Request;
use App\Core\Response;
use App\Domain\Shared\ValueObjects\Uuid;
use App\Domain\Users\Entities\User;
use App\Domain\Users\Repositories\UserRepositoryInterface;
use Exception;
use InvalidArgumentException;

class SellerController
{
    public function __construct(
        private SearchClientByNationalId $searchClientByNationalId,
        private RegisterClientBySeller $registerClientBySeller,
        private RegisterVehicle $registerVehicle,
        private CheckoutServiceContract $checkoutServiceContract,
        private ListSellerContracts $listSellerContracts,
        private RenewServiceContract $renewServiceContract,
        private UserRepositoryInterface $userRepository
    ) {}

    /**
     * GET /api/v1/seller/clients/search?identification_card=V-12345678
     * Busca un cliente por su número de cédula (usando Redis + índice único en MariaDB)
     * y devuelve su perfil, vehículos registrados y contratos (activos o vencidos para renovación).
     */
    public function searchClient(Request $request): void
    {
        $rawNationalId = (string)($request->query('identification_card') ?? $request->query('q') ?? '');
        if (trim($rawNationalId) === '') {
            Response::json(
                ['identification_card' => 'Debe proporcionar el parámetro identification_card (ej. V-12345678).'],
                422,
                'fail',
                'Parámetro de búsqueda requerido.'
            );
            return;
        }

        try {
            $result = $this->searchClientByNationalId->execute($rawNationalId);
            $msg = $result['found']
                ? 'Cliente encontrado en el sistema.'
                : 'No se encontró ningún cliente con la cédula ingresada. Puede proceder a registrarlo.';
            Response::json($result, 200, 'success', $msg);
        } catch (Exception $e) {
            $code = (is_int($e->getCode()) && $e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 422;
            Response::json([], $code, 'fail', $e->getMessage());
        }
    }

    /**
     * POST /api/v1/seller/clients
     * Registra un nuevo cliente desde la oficina comercial / WhatsApp (por OCR de cédula o formulario),
     * marcando su cédula como VERIFIED automáticamente y enviándole su contraseña aleatoria por correo.
     */
    public function registerClient(Request $request): void
    {
        /** @var User|null $seller */
        $seller = $request->user();
        if ($seller === null || $seller->getId() === null) {
            Response::json([], 401, 'fail', 'No autorizado.');
            return;
        }

        $email = (string)($request->input('email') ?? '');
        $phone = (string)($request->input('phone') ?? '');
        $nationalId = $request->input('identification_card') ? (string)$request->input('identification_card') : null;
        $names = $request->input('names') ? (string)$request->input('names') : null;
        $surnames = $request->input('surnames') ? (string)$request->input('surnames') : null;
        $birthDate = $request->input('birth_date') ? (string)$request->input('birth_date') : null;

        if (trim($email) === '' || trim($phone) === '') {
            Response::json(
                ['email' => 'El correo electrónico y el teléfono del cliente son obligatorios.'],
                422,
                'fail',
                'Datos incompletos.'
            );
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
            $targetName = sprintf('seller_cedula_%d_%s.%s', $seller->getId(), bin2hex(random_bytes(8)), $ext ?: 'jpg');
            $targetPath = $uploadsDir . '/' . $targetName;
            if (move_uploaded_file($uploaded['tmp_name'], $targetPath)) {
                $filePath = $targetPath;
            }
        } elseif (!empty($request->input('file_path'))) {
            $filePath = (string)$request->input('file_path');
        }

        try {
            $result = $this->registerClientBySeller->execute(
                rawEmail: $email,
                rawPhone: $phone,
                documentFilePath: $filePath,
                rawNationalId: $nationalId,
                rawNames: $names,
                rawSurnames: $surnames,
                rawBirthDate: $birthDate,
                sellerName: $seller->getFullName()
            );

            Response::json($result, 201, 'success', 'Cliente registrado y verificado exitosamente por la vendedora.');
        } catch (Exception $e) {
            $code = (is_int($e->getCode()) && $e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 422;
            Response::json([], $code, 'fail', $e->getMessage());
        }
    }

    /**
     * POST /api/v1/seller/vehicles
     * Registra un vehículo para un cliente específico desde la gestión de la vendedora,
     * marcándolo automáticamente como verificado (is_verified = true, status = active) y su documento como VERIFIED.
     */
    public function registerVehicle(Request $request): void
    {
        /** @var User|null $seller */
        $seller = $request->user();
        if ($seller === null || $seller->getId() === null) {
            Response::json([], 401, 'fail', 'No autorizado.');
            return;
        }

        $clientId = $this->resolveClientId($request->input('client_id') ?? $request->input('user_id'));
        if ($clientId === null) {
            Response::json(
                ['client_id' => 'Debe proporcionar un client_id (ID numérico o UUID v7) válido del cliente.'],
                422,
                'fail',
                'Cliente no especificado.'
            );
            return;
        }

        $carnetFilePath = $request->input('carnet_file_path') ? (string)$request->input('carnet_file_path') : null;
        if ($carnetFilePath === null && !empty($_FILES['document']['tmp_name'])) {
            $uploaded = $_FILES['document'];
            $ext = pathinfo($uploaded['name'], PATHINFO_EXTENSION);
            $uploadsDir = dirname(__DIR__, 2) . '/storage/documents';
            if (!is_dir($uploadsDir)) {
                mkdir($uploadsDir, 0755, true);
            }
            $targetName = sprintf('seller_carnet_%d_%s.%s', $clientId, bin2hex(random_bytes(8)), $ext ?: 'jpg');
            $targetPath = $uploadsDir . '/' . $targetName;
            if (move_uploaded_file($uploaded['tmp_name'], $targetPath)) {
                $carnetFilePath = $targetPath;
            }
        }

        try {
            $result = $this->registerVehicle->execute(
                userId: $clientId,
                rawPlate: (string)($request->input('plate_number') ?? ''),
                rawNiv: (string)($request->input('niv') ?? ''),
                year: (int)($request->input('year') ?? 0),
                color: (string)($request->input('color') ?? ''),
                rawEngineSerial: $request->input('engine_serial') ? (string)$request->input('engine_serial') : null,
                brandId: $request->input('brand_id') !== null ? (int)$request->input('brand_id') : null,
                modelId: $request->input('model_id') !== null ? (int)$request->input('model_id') : null,
                vehicleClassId: $request->input('vehicle_class_id') !== null ? (int)$request->input('vehicle_class_id') : null,
                vehicleSubclassId: $request->input('vehicle_subclass_id') !== null ? (int)$request->input('vehicle_subclass_id') : null,
                brandName: $request->input('brand_name') ? (string)$request->input('brand_name') : null,
                modelName: $request->input('model_name') ? (string)$request->input('model_name') : null,
                carnetFilePath: $carnetFilePath,
                documentType: $request->input('document_type') ? (string)$request->input('document_type') : 'vehicle_registration',
                autoVerifyBySeller: true
            );

            Response::json($result, 201, 'success', 'Vehículo registrado y verificado exitosamente para el cliente.');
        } catch (Exception $e) {
            $code = (is_int($e->getCode()) && $e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 422;
            Response::json([], $code, 'fail', $e->getMessage());
        }
    }

    /**
     * POST /api/v1/seller/contracts
     * Crea un Contrato de Servicio en estado PENDING_PAYMENT y su Orden de Pago anual
     * para el vehículo del cliente, vinculando a la vendedora (seller_id).
     */
    public function createContract(Request $request): void
    {
        /** @var User|null $seller */
        $seller = $request->user();
        if ($seller === null || $seller->getId() === null) {
            Response::json([], 401, 'fail', 'No autorizado.');
            return;
        }

        $clientId = $this->resolveClientId($request->input('client_id') ?? $request->input('user_id'));
        $planId = $request->input('plan_id');
        $vehicleId = $request->input('vehicle_id');

        if ($clientId === null || empty($planId)) {
            Response::json(
                ['client_id' => 'Los campos client_id y plan_id son obligatorios.'],
                422,
                'fail',
                'Datos incompletos.'
            );
            return;
        }

        try {
            $result = $this->checkoutServiceContract->execute(
                userId: $clientId,
                planIdentifier: $planId,
                vehicleIdentifier: $vehicleId,
                sellerId: $seller->getId()
            );

            Response::json(
                $result,
                201,
                'success',
                'Contrato preliminar (con marca de agua NO PAGADO) y orden de pago generados exitosamente.'
            );
        } catch (Exception $e) {
            $code = (is_int($e->getCode()) && $e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 422;
            Response::json([], $code, 'fail', $e->getMessage());
        }
    }

    /**
     * GET /api/v1/seller/contracts?status=EXPIRED|ACTIVE|PENDING_PAYMENT
     * Lista los contratos vendidos por la vendedora autenticada (para control de comisiones y renovaciones).
     */
    public function myContracts(Request $request): void
    {
        /** @var User|null $seller */
        $seller = $request->user();
        if ($seller === null || $seller->getId() === null) {
            Response::json([], 401, 'fail', 'No autorizado.');
            return;
        }

        $statusFilter = $request->query('status') !== null ? (string)$request->query('status') : null;
        $contracts = $this->listSellerContracts->execute($seller->getId(), $statusFilter);

        Response::json($contracts, 200, 'success', 'Listado de contratos de la vendedora consultado exitosamente.');
    }

    /**
     * POST /api/v1/seller/contracts/{id}/renew
     * Genera una nueva Orden de Pago anual para renovar un contrato vencido del cliente.
     */
    public function renewContract(Request $request, string $id): void
    {
        /** @var User|null $seller */
        $seller = $request->user();
        if ($seller === null || $seller->getId() === null) {
            Response::json([], 401, 'fail', 'No autorizado.');
            return;
        }

        try {
            $result = $this->renewServiceContract->execute(
                contractIdentifier: $id,
                actorUserId: $seller->getId(),
                isSeller: true,
                isAdmin: $seller->hasRole('admin')
            );

            Response::json($result, 201, 'success', 'Orden de renovación anual generada exitosamente.');
        } catch (Exception $e) {
            $code = (is_int($e->getCode()) && $e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 422;
            Response::json([], $code, 'fail', $e->getMessage());
        }
    }

    private function resolveClientId(mixed $rawIdentifier): ?int
    {
        if ($rawIdentifier === null || $rawIdentifier === '') {
            return null;
        }

        $str = trim((string)$rawIdentifier);
        if (ctype_digit($str)) {
            $user = $this->userRepository->findById((int)$str);
            return $user?->getId();
        }

        try {
            $user = $this->userRepository->findByUuid(Uuid::fromString($str));
            return $user?->getId();
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
