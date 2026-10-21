<?php

namespace App\Controllers;

use App\Application\Contracts\UseCases\CancelServiceContract;
use App\Application\Contracts\UseCases\CheckoutServiceContract;
use App\Application\Contracts\UseCases\ExpireOverdueContracts;
use App\Application\Contracts\UseCases\GenerateServiceContractPdf;
use App\Application\Payments\UseCases\HandleBancaribeWebhook;
use App\Application\Contracts\UseCases\ListUserContractsAndOrders;
use App\Application\Payments\UseCases\ProcessPayment;
use App\Application\Contracts\UseCases\RedeemServiceContract;
use App\Application\Contracts\UseCases\RenewServiceContract;
use App\Core\Request;
use App\Core\Response;
use App\Domain\Users\Entities\User;
use App\Domain\Payments\Services\BalanceGatewayInterface;
use App\Domain\Payments\Services\ExchangeRateServiceInterface;
use Exception;

class PaymentController
{
    public function __construct(
        private CheckoutServiceContract $checkoutServiceContract,
        private ProcessPayment $processPayment,
        private HandleBancaribeWebhook $handleWebhook,
        private ListUserContractsAndOrders $listContractsAndOrders,
        private ExchangeRateServiceInterface $exchangeRateService,
        private BalanceGatewayInterface $balanceGateway,
        private ?GenerateServiceContractPdf $generateContractPdf = null,
        private ?CancelServiceContract $cancelContractUseCase = null,
        private ?ExpireOverdueContracts $expireOverdueContractsUseCase = null,
        private ?RenewServiceContract $renewContractUseCase = null,
        private ?RedeemServiceContract $redeemContractUseCase = null
    ) {}

    /**
     * GET /api/v1/exchange-rates
     * Consulta las tasas de cambio BCV vigentes en caché Redis / MariaDB
     */
    public function exchangeRates(Request $request): void
    {
        try {
            $rates = $this->exchangeRateService->getAllCurrentRates();
            Response::json($rates, 200, 'success', 'Tasas de cambio consultadas exitosamente.');
        } catch (Exception $e) {
            $code = (is_int($e->getCode()) && $e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 503;
            Response::json([], $code, 'fail', $e->getMessage());
        }
    }

    /**
     * POST /api/v1/exchange-rates/sync
     * Fuerza la sincronización de tasas BCV con el microservicio de Bancaribe
     */
    public function syncExchangeRates(Request $request): void
    {
        try {
            $synced = $this->exchangeRateService->fetchAndSaveTodayRates();
            Response::json(['synced_rates' => $synced], 200, 'success', 'Tasas de cambio sincronizadas con Bancaribe exitosamente.');
        } catch (Exception $e) {
            $code = (is_int($e->getCode()) && $e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 502;
            Response::json([], $code, 'fail', $e->getMessage());
        }
    }

    /**
     * POST /api/v1/checkout  (o POST /api/v1/contracts/checkout)
     * Inicia la intención de compra de un Plan y genera la orden en Bolívares
     */
    public function checkout(Request $request): void
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($user === null || $user->getId() === null) {
            Response::json([], 401, 'fail', 'No autorizado.');
            return;
        }

        $planId = $request->input('plan_id');
        $vehicleId = $request->input('vehicle_id');

        if (empty($planId)) {
            Response::json(['plan_id' => 'El campo plan_id (UUID o ID del plan) es obligatorio.'], 422, 'fail', 'Datos incompletos.');
            return;
        }

        try {
            $result = $this->checkoutServiceContract->execute(
                userId: $user->getId(),
                planIdentifier: $planId,
                vehicleIdentifier: $vehicleId
            );
            Response::json($result, 201, 'success', 'Intención de compra y orden de pago generadas exitosamente.');
        } catch (Exception $e) {
            $code = (is_int($e->getCode()) && $e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 400;
            Response::json([], $code, 'fail', $e->getMessage());
        }
    }

    /**
     * POST /api/v1/payments/process
     * Procesa un pago mediante C2P o verifica un Pago Móvil (Consulta Operaciones Plus) en Bancaribe
     */
    public function process(Request $request): void
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($user === null || $user->getId() === null) {
            Response::json([], 401, 'fail', 'No autorizado.');
            return;
        }

        $body = $request->body();
        $orderId = $body['order_id'] ?? $body['order_uuid'] ?? $body['order_number'] ?? null;

        if (empty($orderId)) {
            Response::json(['order_id' => 'Debe indicar el order_id, order_uuid u order_number a pagar.'], 422, 'fail', 'Falta identificador de la orden.');
            return;
        }

        // Autocompletar documento de identidad desde el perfil del usuario si no se envió en el body
        $idCard = $user->getIdentificationCard()->getValue(); // ej. V-80123456
        $parts = explode('-', $idCard);
        $defaultDocType = $parts[0] ?? 'V';
        $defaultDocNum = $parts[1] ?? preg_replace('/[^0-9]/', '', $idCard);

        $body['documentType'] ??= $defaultDocType;
        $body['documentNumber'] ??= $defaultDocNum;
        $body['identificadorPersona'] ??= ($defaultDocType . $defaultDocNum);
        $body['telefonoPersona'] ??= $user->getPhone()->getValue();
        $body['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        try {
            $result = $this->processPayment->execute(
                userId: $user->getId(),
                orderIdentifier: $orderId,
                data: $body,
                isSeller: $user->hasRole('seller'),
                isAdmin: $user->hasRole('admin')
            );
            Response::json($result, 200, 'success', 'Pago verificado y contrato activado exitosamente.');
        } catch (Exception $e) {
            $code = (is_int($e->getCode()) && $e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 422;
            Response::json([], $code, 'fail', $e->getMessage());
        }
    }

    /**
     * POST /api/v1/webhooks/bancaribe
     * Recibe confirmación asíncrona de liquidación o rechazo desde Bancaribe (FR-29)
     */
    public function webhook(Request $request): void
    {
        $payload = $request->body();
        $signature = $request->header('X-Bancaribe-Signature') ?? $request->header('X-Webhook-Signature');

        try {
            $result = $this->handleWebhook->execute($payload, $signature);
            Response::json($result, 200, 'success', $result['message'] ?? 'Webhook procesado.');
        } catch (Exception $e) {
            $code = (is_int($e->getCode()) && $e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 400;
            Response::json([], $code, 'fail', $e->getMessage());
        }
    }

    /**
     * GET /api/v1/contracts
     * Lista los contratos de servicio del usuario autenticado
     */
    public function myContracts(Request $request): void
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($user === null || $user->getId() === null) {
            Response::json([], 401, 'fail', 'No autorizado.');
            return;
        }

        $contracts = $this->listContractsAndOrders->listContracts($user->getId());
        Response::json($contracts, 200, 'success', 'Contratos consultados con éxito.');
    }

    /**
     * GET /api/v1/orders
     * Lista las órdenes de pago del usuario autenticado
     */
    public function myOrders(Request $request): void
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($user === null || $user->getId() === null) {
            Response::json([], 401, 'fail', 'No autorizado.');
            return;
        }

        $orders = $this->listContractsAndOrders->listOrders($user->getId());
        Response::json($orders, 200, 'success', 'Órdenes de pago consultadas con éxito.');
    }

    /**
     * GET /api/v1/admin/bank/balances
     * Consulta los saldos de las cuentas bancarias en Bancaribe (Solo Admin)
     */
    public function adminBankBalances(Request $request): void
    {
        $accountNumber = $request->input('account_number') ? (string)$request->input('account_number') : null;
        $forceRefresh = filter_var($request->input('refresh', false), FILTER_VALIDATE_BOOLEAN);

        try {
            $result = $this->balanceGateway->getBalance($accountNumber, $forceRefresh);
            if (!($result['success'] ?? false)) {
                Response::json($result, 502, 'fail', $result['message'] ?? 'Error al consultar saldos bancarios.');
                return;
            }
            Response::json($result, 200, 'success', 'Saldos bancarios consultados exitosamente.');
        } catch (Exception $e) {
            Response::json([], 502, 'fail', 'Error de conexión con el banco: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/v1/admin/integrations/logs?service=bancaribe&limit=50
     * Visor de logs de servicios de terceros (OCR La Venezolana, Bancaribe, Mapbox, Pusher, S3)
     */
    public function adminIntegrationLogs(Request $request): void
    {
        $service = $request->input('service') !== null ? trim((string)$request->input('service')) : null;
        $limit = (int)$request->input('limit', 50);

        $logger = new \App\Infrastructure\Logging\IntegrationLogger();
        $logs = $logger->readLogs($service, $limit);

        Response::json([
            'filter_service' => $service,
            'count'          => count($logs),
            'logs'           => $logs,
        ], 200, 'success', 'Logs de integraciones de terceros consultados exitosamente.');
    }

    /**
     * GET /api/v1/contracts/{id}/pdf
     * Descarga o visualiza en línea el PDF de 2 páginas del Contrato de Servicio (Plan de Grúa)
     * con código QR de verificación y marcas de agua dinámicas según estado.
     */
    public function downloadContractPdf(Request $request, string $id): void
    {
        if ($this->generateContractPdf === null) {
            Response::json([], 500, 'error', 'El servicio generador de PDF de contratos no está configurado.');
            return;
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8088';
        $baseUrl = $_ENV['APP_URL'] ?? "{$scheme}://{$host}";

        try {
            $result = $this->generateContractPdf->execute($id, $baseUrl);
            $disposition = filter_var($request->input('download', false), FILTER_VALIDATE_BOOLEAN)
                ? 'attachment'
                : 'inline';

            http_response_code(200);
            header('Content-Type: application/pdf');
            header(sprintf('Content-Disposition: %s; filename="%s"', $disposition, $result['filename']));
            header('Content-Length: ' . strlen($result['pdf_binary']));
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            echo $result['pdf_binary'];
            exit;
        } catch (Exception $e) {
            $code = (is_int($e->getCode()) && $e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 404;
            Response::json([], $code, 'fail', $e->getMessage());
        }
    }

    /**
     * POST /api/v1/contracts/{id}/cancel
     * Cancela un contrato de servicio (pasándolo a CANCELLED).
     */
    public function cancelContract(Request $request, string $id): void
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($user === null || $user->getId() === null) {
            Response::json([], 401, 'fail', 'No autorizado.');
            return;
        }

        if ($this->cancelContractUseCase === null) {
            Response::json([], 500, 'error', 'Caso de uso de cancelación no configurado.');
            return;
        }

        try {
            $isAdmin = $user->hasRole('admin');
            $data = $this->cancelContractUseCase->execute($id, $user->getId(), $isAdmin);
            Response::json($data, 200, 'success', 'Contrato cancelado exitosamente.');
        } catch (Exception $e) {
            $code = (is_int($e->getCode()) && $e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 400;
            Response::json([], $code, 'fail', $e->getMessage());
        }
    }

    /**
     * POST /api/v1/contracts/{id}/renew
     * Genera una nueva Orden de Pago anual para renovar un contrato de servicio (por el cliente o por una vendedora).
     */
    public function renewContract(Request $request, string $id): void
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($user === null || $user->getId() === null) {
            Response::json([], 401, 'fail', 'No autorizado.');
            return;
        }

        if ($this->renewContractUseCase === null) {
            Response::json([], 500, 'error', 'Caso de uso de renovación no configurado.');
            return;
        }

        try {
            $result = $this->renewContractUseCase->execute(
                contractIdentifier: $id,
                actorUserId: $user->getId(),
                isSeller: $user->hasRole('seller'),
                isAdmin: $user->hasRole('admin')
            );
            Response::json($result, 201, 'success', 'Orden de renovación generada exitosamente.');
        } catch (Exception $e) {
            $code = (is_int($e->getCode()) && $e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 400;
            Response::json([], $code, 'fail', $e->getMessage());
        }
    }

    /**
     * POST /api/v1/contracts/{id}/redeem
     * Canjea un servicio de grúa sobre un contrato activo (descontando 1 servicio y los kilómetros recorridos).
     */
    public function redeemContract(Request $request, string $id): void
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($user === null || $user->getId() === null) {
            Response::json([], 401, 'fail', 'No autorizado.');
            return;
        }

        if ($this->redeemContractUseCase === null) {
            Response::json([], 500, 'error', 'Caso de uso de canje no configurado.');
            return;
        }

        $distanceKm = (float)($request->input('distance_km', 0.0));

        try {
            $result = $this->redeemContractUseCase->execute(
                contractIdentifier: $id,
                actorUserId: $user->getId(),
                distanceKm: $distanceKm,
                isSellerOrAdmin: $user->hasRole('seller') || $user->hasRole('admin')
            );
            Response::json($result, 200, 'success', 'Servicio de contrato canjeado exitosamente.');
        } catch (Exception $e) {
            $code = (is_int($e->getCode()) && $e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 422;
            Response::json([], $code, 'fail', $e->getMessage());
        }
    }

    /**
     * POST /api/v1/admin/contracts/expire-overdue
     * Ejecuta manualmente el barrido de vencimiento de contratos cuya fecha expires_at ya expiró.
     */
    public function expireOverdueContracts(Request $request): void
    {
        if ($this->expireOverdueContractsUseCase === null) {
            Response::json([], 500, 'error', 'Caso de uso de expiración no configurado.');
            return;
        }

        $result = $this->expireOverdueContractsUseCase->execute();
        Response::json($result, 200, 'success', 'Verificación de contratos vencidos completada.');
    }
}
