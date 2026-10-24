<?php

namespace App\Controllers;

use App\Application\Contracts\UseCases\ListUserContractsAndOrders;
use App\Application\Payments\DTOs\{
    BankBalanceResponseDto,
    ExchangeRatesResponseDto,
    ExchangeRatesSyncResponseDto,
    IntegrationLogsResponseDto,
    OrderResponseDto,
    PaymentProcessResponseDto,
    WebhookResponseDto
};
use App\Application\Payments\UseCases\{HandleBancaribeWebhook, ProcessPayment};
use App\Core\{Request, Response};
use App\Domain\Payments\Services\{BalanceGatewayInterface, ExchangeRateServiceInterface};
use App\Domain\Users\Entities\User;
use App\Infrastructure\Logging\IntegrationLogger;
use Exception;
use PDOException;

class PaymentController
{
    /** Mensaje genérico para errores 5xx: nunca se filtran detalles internos o respuestas crudas del banco. */
    private const GENERIC_SERVER_ERROR = 'Servicio de pagos no disponible temporalmente. Intente más tarde.';

    public function __construct(
        private ProcessPayment $processPayment,
        private HandleBancaribeWebhook $handleWebhook,
        private ListUserContractsAndOrders $listContractsAndOrders,
        private ExchangeRateServiceInterface $exchangeRateService,
        private BalanceGatewayInterface $balanceGateway,
        private IntegrationLogger $integrationLogger
    ) {}

    /**
     * GET /api/v1/exchange-rates
     * Consulta las tasas de cambio BCV vigentes en caché Redis / MariaDB
     */
    public function exchangeRates(Request $request): void
    {
        try {
            $rates = ExchangeRatesResponseDto::fromArray($this->exchangeRateService->getAllCurrentRates());
            Response::json($rates->toArray(), 200, 'success', 'Tasas de cambio consultadas exitosamente.');
        } catch (Exception $e) {
            $this->failFromException($e, 503);
        }
    }

    /**
     * POST /api/v1/exchange-rates/sync
     * Fuerza la sincronización de tasas BCV con el microservicio de Bancaribe (Solo Admin)
     */
    public function syncExchangeRates(Request $request): void
    {
        try {
            $synced = ExchangeRatesSyncResponseDto::fromArray($this->exchangeRateService->fetchAndSaveTodayRates());
            Response::json($synced->toArray(), 200, 'success', 'Tasas de cambio sincronizadas con Bancaribe exitosamente.');
        } catch (Exception $e) {
            // Endpoint exclusivo de administradores: se permite ver el detalle del error del banco
            $this->failFromException($e, 502, exposeServerErrors: true);
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
        $orderId = $this->extractOrderIdentifier($body);

        if ($orderId === null) {
            Response::json(['order_id' => 'Debe indicar el order_id, order_uuid u order_number a pagar.'], 422, 'fail', 'Falta identificador de la orden.');
            return;
        }

        // Autocompletar documento de identidad desde el perfil del usuario si no se envió en el body
        [$defaultDocType, $defaultDocNum] = $this->splitIdentification($user->getIdentificationCard()->getValue());

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
            Response::json(
                PaymentProcessResponseDto::fromArray($result)->toArray(),
                200,
                'success',
                'Pago verificado y contrato activado exitosamente.'
            );
        } catch (Exception $e) {
            $this->failFromException($e, 422);
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
            $result = WebhookResponseDto::fromArray($this->handleWebhook->execute($payload, $signature, $rawPayload));
            Response::json($result->toArray(), 200, 'success', $result->message);
        } catch (Exception $e) {
            $this->failFromException($e, 400);
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

        $orders = array_map(
            static fn(array $order): array => OrderResponseDto::fromArray($order)->toArray(),
            $this->listContractsAndOrders->listOrders($user->getId())
        );
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
            $balance = BankBalanceResponseDto::fromArray($this->balanceGateway->getBalance($accountNumber, $forceRefresh));
            if (!$balance->success) {
                Response::json($balance->toArray(), 502, 'fail', $balance->message ?? 'Error al consultar saldos bancarios.');
                return;
            }
            Response::json($balance->toArray(), 200, 'success', 'Saldos bancarios consultados exitosamente.');
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

        $logs = IntegrationLogsResponseDto::fromLogs($service, $this->integrationLogger->readLogs($service, $limit));

        Response::json($logs->toArray(), 200, 'success', 'Logs de integraciones de terceros consultados exitosamente.');
    }

    /**
     * Responde con un error derivado de una excepción.
     * El código de la excepción solo se usa como HTTP status si es un entero 4xx/5xx válido;
     * cualquier otro valor (códigos de driver, SQLSTATE, 0...) se sustituye por el código por defecto.
     * Los errores 5xx y de base de datos nunca exponen su mensaje interno salvo que se autorice explícitamente.
     */
    private function failFromException(Exception $e, int $defaultCode, bool $exposeServerErrors = false): void
    {
        $code = ($e instanceof PDOException) ? 500 : $this->resolveHttpCode($e->getCode(), $defaultCode);
        $message = ($code >= 500 && !$exposeServerErrors) ? self::GENERIC_SERVER_ERROR : $e->getMessage();

        Response::json([], $code, 'fail', $message);
    }

    private function resolveHttpCode(mixed $exceptionCode, int $defaultCode): int
    {
        return (is_int($exceptionCode) && $exceptionCode >= 400 && $exceptionCode < 600)
            ? $exceptionCode
            : $defaultCode;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function extractOrderIdentifier(array $body): string|int|null
    {
        $orderId = $body['order_id'] ?? $body['order_uuid'] ?? $body['order_number'] ?? null;

        if (is_int($orderId)) {
            return $orderId;
        }
        if (is_string($orderId) && trim($orderId) !== '') {
            return trim($orderId);
        }

        return null;
    }

    /**
     * @return array{0: string, 1: string} [tipo de documento, número de documento]
     */
    private function splitIdentification(string $idCard): array
    {
        if (preg_match('/^([A-Za-z])-?(\d+)$/', trim($idCard), $matches) === 1) {
            return [strtoupper($matches[1]), $matches[2]];
        }

        return ['V', (string)preg_replace('/[^0-9]/', '', $idCard)];
    }
}
