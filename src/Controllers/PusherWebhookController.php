<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Infrastructure\Database\Repositories\Towing\MariaDbTowServiceRepository;
use App\Infrastructure\Logging\IntegrationLogger;
use Exception;

class PusherWebhookController
{
    public function __construct(
        private string $appKey,
        private string $appSecret,
        private ?IntegrationLogger $logger = null
    ) {
        $this->logger = $logger ?? new IntegrationLogger();
    }

    /**
     * POST /api/v1/webhooks/pusher
     * Maneja webhooks de Pusher Channels (Channel existence: channel_occupied / channel_vacated)
     */
    public function handle(Request $request, ?string $rawBody = null): void
    {
        if ($rawBody === null || $rawBody === '') {
            $input = file_get_contents('php://input');
            $rawBody = ($input !== false && $input !== '')
                ? $input
                : json_encode($request->body(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $headerKey = $request->header('X-Pusher-Key') ?? $request->header('x-pusher-key');
        $headerSignature = $request->header('X-Pusher-Signature') ?? $request->header('x-pusher-signature');

        // 1. Validar autenticidad de la petición con HMAC-SHA256 (Pusher Webhook Security)
        if (empty($headerKey) || empty($headerSignature) || $headerKey !== $this->appKey) {
            Response::json([], 401, 'fail', 'Credenciales o firma del webhook de Pusher inválidas.');
            return;
        }

        $expectedSignature = hash_hmac('sha256', $rawBody, $this->appSecret);
        if (!hash_equals($expectedSignature, $headerSignature)) {
            Response::json([], 401, 'fail', 'Firma criptográfica del webhook de Pusher no coincide.');
            return;
        }

        $payload = json_decode($rawBody, true);
        if (!is_array($payload) || !isset($payload['events']) || !is_array($payload['events'])) {
            Response::json([], 422, 'fail', 'Payload del webhook de Pusher con formato inválido.');
            return;
        }

        $processedEvents = [];

        // 2. Procesar eventos de ciclo de vida de canales (Channel existence)
        foreach ($payload['events'] as $event) {
            $name = (string)($event['name'] ?? '');
            $channel = (string)($event['channel'] ?? '');

            $processedEvents[] = [
                'event'   => $name,
                'channel' => $channel,
            ];

            // Registrar telemetría de presencia/desconexión en logs
            $this->logger->log(
                service: 'pusher',
                operation: 'WEBHOOK ' . strtoupper($name),
                url: '/api/v1/webhooks/pusher',
                statusCode: 200,
                durationMs: 0.5,
                requestPayload: $event,
                responsePayload: ['status' => 'processed']
            );
        }

        Response::json([
            'processed' => count($processedEvents),
            'events'    => $processedEvents,
        ], 200, 'success', 'Webhook de Pusher procesado exitosamente.');
    }
}
