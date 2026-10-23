<?php

namespace App\Infrastructure\Services\Bancaribe;

use App\Domain\Payments\Entities\Order;
use App\Domain\Payments\Services\ExchangeRateServiceInterface;
use App\Domain\Payments\Services\PaymentGatewayInterface;
use App\Domain\Payments\ValueObjects\PaymentResult;
use App\Infrastructure\Http\HttpClientInterface;
use App\Infrastructure\Logging\IntegrationLogger;
use Throwable;

class BancaribePaymentService implements PaymentGatewayInterface
{
    private IntegrationLogger $logger;

    public function __construct(
        private BancaribeAuthService $authService,
        private ExchangeRateServiceInterface $exchangeService,
        private HttpClientInterface $httpClient,
        private string $baseUrl = '',
        private string $c2pEndpoint = 'C2P/1.0.0/registrarPagoC2pApi',
        private string $plusEndpoint = 'COP/1.0.0/consulta-operacion',
        private string $c2pHash = '',
        private string $plusHash = '',
        private string $rifComercio = 'J502098500',
        private string $telefonoComercio = '04267536320',
        private string $nombreComercio = 'UbikGo',
        private bool $sandboxMode = false,
        ?IntegrationLogger $logger = null,
        private bool $isLocalEnv = true
    ) {
        if ($this->sandboxMode && !$this->isLocalEnv) {
            throw new \LogicException('El modo sandbox de pagos Bancaribe no está permitido fuera del entorno local.');
        }
        $this->logger = $logger ?? new IntegrationLogger();
    }

    public function processC2p(Order $order, array $paymentData): PaymentResult
    {
        $token = $this->authService->getAccessToken();
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($this->c2pEndpoint, '/');

        $amountVes = $order->getAmountVes()->getAmount();
        if ($amountVes <= 0) {
            $rateVenta = $this->exchangeService->getExchangeRate('USD', 'venta');
            $amountVes = round($order->getAmountUsd()->getAmount() * $rateVenta, 2);
        }

        $payload = $this->buildC2pPayload($order, $paymentData, $token, $amountVes);

        // Modo Sandbox local para pruebas sin VPN bancaria
        if ($this->sandboxMode) {
            $res = $this->simulateC2pResponse($payload, $paymentData);
            $this->logger->log(
                service: 'bancaribe',
                operation: 'SANDBOX POST C2P',
                url: $url,
                statusCode: $res->success ? 200 : 422,
                durationMs: 1.5,
                requestPayload: $payload,
                responsePayload: $res->responsePayload,
                error: $res->success ? null : $res->message
            );
            return $res;
        }

        try {
            $response = $this->httpClient->postJson(
                url: $url,
                data: $payload,
                headers: [
                    'Authorization' => "Bearer {$token}",
                    'Accept'        => 'application/json',
                ]
            );
        } catch (Throwable $e) {
            return new PaymentResult(
                success: false,
                message: 'Error de conexión con la pasarela C2P de Bancaribe: ' . $e->getMessage(),
                requestPayload: $payload,
                responsePayload: ['error' => $e->getMessage()]
            );
        }

        $data = $response->json();

        if (!$response->isSuccessful()) {
            $errorMsg = $data['error'] ?? $data['message'] ?? $response->body();
            return new PaymentResult(
                success: false,
                message: (string)$errorMsg,
                requestPayload: $payload,
                responsePayload: is_array($data) ? $data : ['raw' => $response->body()]
            );
        }

        $out = $data['Envelope']['Body']['registrarPagoC2pApiResponse']['out'] ?? [];
        $errorCode = $out['codigoError'] ?? null;
        $description = (string)($out['descripcionError'] ?? 'Error desconocido en C2P');

        if ($errorCode === 0 || $errorCode === '0') {
            return new PaymentResult(
                success: true,
                message: $description ?: 'Pago C2P procesado exitosamente.',
                transactionId: isset($out['secuencial']) ? (string)$out['secuencial'] : null,
                requestPayload: $payload,
                responsePayload: is_array($data) ? $data : []
            );
        }

        return new PaymentResult(
            success: false,
            message: $description,
            requestPayload: $payload,
            responsePayload: is_array($data) ? $data : []
        );
    }

    public function verifyPagoMovil(
        string $reference,
        string $date,
        float $amount,
        string $identificadorPersona,
        ?string $telefono = null
    ): PaymentResult {
        $token = $this->authService->getAccessToken();
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($this->plusEndpoint, '/');

        $montoFormateado = number_format($amount, 2, '.', '');
        $normalizedId = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $identificadorPersona));

        $payload = [
            'tipoTrx'              => 'PM',
            'montoTransaccion'     => $montoFormateado,
            'rif'                  => $this->rifComercio,
            'identificadorPersona' => $normalizedId,
            'referencia'           => trim($reference),
            'telefonoDebito'       => $telefono ?? '',
            'factura'              => '',
            'fecha'                => $date === date('Y-m-d') ? '' : date('m/d/Y', strtotime($date)),
            'hash'                 => $this->plusHash,
            'numCuenta'            => null,
            'IO'                   => null,
            'BCV'                  => null,
        ];

        // Modo Sandbox local para pruebas sin VPN bancaria
        if ($this->sandboxMode) {
            $res = $this->simulatePagoMovilResponse($payload, $reference, $amount);
            $this->logger->log(
                service: 'bancaribe',
                operation: 'SANDBOX POST PAGO_MOVIL_PLUS',
                url: $url,
                statusCode: $res->success ? 200 : 422,
                durationMs: 1.2,
                requestPayload: $payload,
                responsePayload: $res->responsePayload,
                error: $res->success ? null : $res->message
            );
            return $res;
        }

        try {
            $response = $this->httpClient->postJson(
                url: $url,
                data: $payload,
                headers: [
                    'Authorization' => "Bearer {$token}",
                    'Accept'        => 'application/json',
                ]
            );
        } catch (Throwable $e) {
            return new PaymentResult(
                success: false,
                message: 'Error de conexión con Consulta Operaciones Plus de Bancaribe: ' . $e->getMessage(),
                requestPayload: $payload,
                responsePayload: ['error' => $e->getMessage()]
            );
        }

        $responseBody = $response->json();

        if (!$response->isSuccessful()) {
            $msg = is_array($responseBody)
                ? ($responseBody['codigo'] ?? $responseBody['message'] ?? 'Error de conexión con el banco.')
                : 'Error de conexión con el banco.';

            return new PaymentResult(
                success: false,
                message: (string)$msg,
                requestPayload: $payload,
                responsePayload: is_array($responseBody) ? $responseBody : ['raw' => $response->body()]
            );
        }

        if (isset($responseBody['success']) && $responseBody['success'] === false) {
            $msg = $responseBody['data']['messages']['message'] ?? 'No existe la operación reportada en el banco.';
            return new PaymentResult(
                success: false,
                message: (string)$msg,
                requestPayload: $payload,
                responsePayload: $responseBody
            );
        }

        $data = $responseBody['data'] ?? [];
        $isSuccess = (($responseBody['success'] ?? false) === true) && ((string)($data['ejecucion'] ?? '') === '0');

        if ($isSuccess) {
            $bankAmount = (float)($data['valor'] ?? 0);

            if ($bankAmount >= $amount) {
                return new PaymentResult(
                    success: true,
                    message: 'Pago Móvil verificado correctamente.',
                    transactionId: isset($data['secuencial']) ? (string)$data['secuencial'] : $reference,
                    requestPayload: $payload,
                    responsePayload: $responseBody
                );
            }

            return new PaymentResult(
                success: false,
                message: "El pago existe pero el monto es insuficiente. Esperado: {$amount}, Recibido: {$bankAmount}",
                requestPayload: $payload,
                responsePayload: $responseBody
            );
        }

        return new PaymentResult(
            success: false,
            message: (string)($data['descripcion'] ?? 'No se encontró el pago o fue rechazado por el banco.'),
            requestPayload: $payload,
            responsePayload: is_array($responseBody) ? $responseBody : []
        );
    }

    /**
     * @param array<string, mixed> $paymentData
     * @return array<string, mixed>
     */
    private function buildC2pPayload(Order $order, array $paymentData, string $token, float $amountVes): array
    {
        $docType = strtoupper(trim((string)($paymentData['documentType'] ?? 'V')));
        $docNumber = preg_replace('/[^A-Za-z0-9]/', '', (string)($paymentData['documentNumber'] ?? ''));

        return [
            'registrarPagoC2pApi' => [
                'request' => [
                    'anulacion'            => 'N',
                    'bancoCredito'         => '0114',
                    'bancoPagador'         => (string)($paymentData['bancoPagador'] ?? ''),
                    'cajaTerminal'         => 1,
                    'canalVirtual'         => 14,
                    'codigoMoneda'         => 928,
                    'concepto'             => "Pago de Orden #{$order->getOrderNumber()}",
                    'direccionInternet'    => (string)($paymentData['ip_address'] ?? '127.0.0.1'),
                    'hash'                 => $this->c2pHash,
                    'identificadorExterno' => $token,
                    'identificadorPersona' => "{$docType}{$docNumber}",
                    'montoTransaccion'     => $amountVes,
                    'nombreComercio'       => $this->nombreComercio,
                    'numeroReferencia'     => 'null',
                    'otp'                  => (string)($paymentData['otp'] ?? ''),
                    'rif'                  => $this->rifComercio,
                    'sucursal'             => 1,
                    'telefonoCredito'      => $this->telefonoComercio,
                    'telefonoPersona'      => (string)($paymentData['telefonoPersona'] ?? ''),
                    'terminal'             => 'ubikgo 001',
                    'tipoTerminal'         => 'WEB',
                    'vendedor'             => 0,
                ],
            ],
        ];
    }

    private function simulateC2pResponse(array $payload, array $paymentData): PaymentResult
    {
        $otp = (string)($paymentData['otp'] ?? '');
        if ($otp === '000000') {
            $fakeFail = [
                'Envelope' => [
                    'Body' => [
                        'registrarPagoC2pApiResponse' => [
                            'out' => [
                                'codigoError'      => 15,
                                'descripcionError' => 'CLAVE DINAMICA OTP INVALIDA O EXPIRADA',
                                'secuencial'       => null,
                            ],
                        ],
                    ],
                ],
            ];
            return new PaymentResult(
                success: false,
                message: 'CLAVE DINAMICA OTP INVALIDA O EXPIRADA',
                requestPayload: $payload,
                responsePayload: $fakeFail
            );
        }

        $secuencial = 'C2P' . date('YmdHis') . random_int(100, 999);
        $fakeSuccess = [
            'Envelope' => [
                'Body' => [
                    'registrarPagoC2pApiResponse' => [
                        'out' => [
                            'codigoError'      => 0,
                            'descripcionError' => 'EXITO',
                            'secuencial'       => $secuencial,
                        ],
                    ],
                ],
            ],
        ];

        return new PaymentResult(
            success: true,
            message: 'EXITO',
            transactionId: $secuencial,
            requestPayload: $payload,
            responsePayload: $fakeSuccess
        );
    }

    private function simulatePagoMovilResponse(array $payload, string $reference, float $amount): PaymentResult
    {
        if ($reference === '00000000') {
            $fakeFail = [
                'success' => false,
                'data'    => [
                    'messages' => ['message' => 'No existe operación con la referencia suministrada.'],
                ],
            ];
            return new PaymentResult(
                success: false,
                message: 'No existe operación con la referencia suministrada.',
                requestPayload: $payload,
                responsePayload: $fakeFail
            );
        }

        $fakeSuccess = [
            'success' => true,
            'data'    => [
                'ejecucion'  => '0',
                'valor'      => $amount,
                'secuencial' => $reference,
            ],
        ];

        return new PaymentResult(
            success: true,
            message: 'Pago Móvil verificado correctamente.',
            transactionId: $reference,
            requestPayload: $payload,
            responsePayload: $fakeSuccess
        );
    }
}
