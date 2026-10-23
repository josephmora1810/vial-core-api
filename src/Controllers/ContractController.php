<?php

namespace App\Controllers;

use App\Application\Contracts\UseCases\CancelServiceContract;
use App\Application\Contracts\UseCases\CheckoutServiceContract;
use App\Application\Contracts\UseCases\ExpireOverdueContracts;
use App\Application\Contracts\UseCases\GenerateServiceContractPdf;
use App\Application\Contracts\UseCases\ListUserContractsAndOrders;
use App\Application\Contracts\UseCases\RedeemServiceContract;
use App\Application\Contracts\UseCases\RenewServiceContract;
use App\Application\Contracts\UseCases\VerifyServiceContract;
use App\Core\Request;
use App\Core\Response;
use App\Domain\Users\Entities\User;
use Exception;

class ContractController
{
    public function __construct(
        private CheckoutServiceContract $checkoutServiceContract,
        private ListUserContractsAndOrders $listContractsAndOrders,
        private GenerateServiceContractPdf $generateContractPdf,
        private CancelServiceContract $cancelContractUseCase,
        private ExpireOverdueContracts $expireOverdueContractsUseCase,
        private RenewServiceContract $renewContractUseCase,
        private RedeemServiceContract $redeemContractUseCase,
        private VerifyServiceContract $verifyContractUseCase
    ) {}

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
     * GET /api/v1/contracts/{id}/pdf
     * Descarga o visualiza en línea el PDF de 2 páginas del Contrato de Servicio (Plan de Grúa)
     * con código QR de verificación y marcas de agua dinámicas según estado.
     */
    public function downloadContractPdf(Request $request, string $id): void
    {
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
     * GET /api/v1/contracts/verify/{code}
     * Validación pública y autoritativa de contratos de grúa contra clonación y fraude por IA.
     * Permite validar la autenticidad del documento escaneando el código QR oficial.
     */
    public function verifyContract(Request $request, string $code): void
    {
        // Si la petición proviene de un navegador web/móvil (cámara escaneando QR) o solicita PDF explícito (?pdf=1)
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $wantsPdf = filter_var($request->input('pdf', false), FILTER_VALIDATE_BOOLEAN)
            || (str_contains($accept, 'text/html') && !str_contains($accept, 'application/json'));

        if ($wantsPdf) {
            $this->downloadContractPdf($request, $code);
            return;
        }

        try {
            $plate = $request->input('plate');
            $result = $this->verifyContractUseCase->execute($code, $plate ? (string)$plate : null);
            $statusCode = $result['fraud_alert'] ? 409 : 200;
            Response::json($result, $statusCode, $result['status'], $result['fraud_alert'] ? 'Alerta de inconsistencia en verificación.' : 'Contrato verificado exitosamente.');
        } catch (Exception $e) {
            $codeHttp = (is_int($e->getCode()) && $e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 404;
            Response::json([], $codeHttp, 'fail', $e->getMessage());
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
    public function expireOverdueContracts(Request $_): void
    {
        $result = $this->expireOverdueContractsUseCase->execute();
        Response::json($result, 200, 'success', 'Verificación de contratos vencidos completada.');
    }
}
