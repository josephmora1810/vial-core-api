<?php

namespace App\Controllers;

use App\Application\Payments\UseCases\HandleBancaribeWebhook;
use App\Application\Contracts\UseCases\ListUserContractsAndOrders;
use App\Application\Payments\UseCases\ProcessPayment;
use App\Core\Request;
use App\Core\Response;
use App\Domain\Users\Entities\User;
use App\Domain\Payments\Services\BalanceGatewayInterface;
use App\Domain\Payments\Services\ExchangeRateServiceInterface;
use App\Infrastructure\Logging\IntegrationLogger;
use Exception;

class PaymentController
{
    public function __construct(
        private ProcessPayment $processPayment,
        private HandleBancaribeWebhook $handleWebhook,
        private ListUserContractsAndOrders $listContractsAndOrders,
        private ExchangeRateServiceInterface $exchangeRateService,
        private BalanceGatewayInterface $balanceGateway
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
        $rawBody = file_get_contents('php://input');
        $rawPayload = ($rawBody !== false && $rawBody !== '') ? $rawBody : json_encode($payload, JSON_UNESCAPED_UNICODE);
        $signature = $request->header('X-Bancaribe-Signature') ?? $request->header('X-Webhook-Signature');

        try {
            $result = $this->handleWebhook->execute($payload, $signature, $rawPayload);
            Response::json($result, 200, 'success', $result['message'] ?? 'Webhook procesado.');
        } catch (Exception $e) {
            $code = (is_int($e->getCode()) && $e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 400;
            Response::json([], $code, 'fail', $e->getMessage());
        }
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
     * Visor de logs de servicios de terceros (OCR La Venezolana, Bancaribe, OSRM, Pusher, S3)
     */
    public function adminIntegrationLogs(Request $request): void
    {
        $service = $request->input('service') !== null ? trim((string)$request->input('service')) : null;
        $limit = (int)$request->input('limit', 50);

        $logger = new IntegrationLogger();
        $logs = $logger->readLogs($service, $limit);

        Response::json([
            'filter_service' => $service,
            'count'          => count($logs),
            'logs'           => $logs,
        ], 200, 'success', 'Logs de integraciones de terceros consultados exitosamente.');
    }
}
