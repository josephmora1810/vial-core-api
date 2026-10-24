<?php

namespace App\Controllers;

use App\Application\Commissions\DTOs\{
    AdminCommissionsSummaryResponseDto,
    CommissionResponseDto,
    SellerProfileResponseDto
};
use App\Application\Commissions\UseCases\{
    ListAdminCommissions,
    PayCommissionByAdmin,
    UpdateSellerCommissionRate
};
use App\Core\{Request, Response};
use App\Domain\Users\Entities\User;
use Exception;
use InvalidArgumentException;

class AdminCommissionController
{
    public function __construct(
        private ListAdminCommissions $listAdminCommissions,
        private PayCommissionByAdmin $payCommissionByAdmin,
        private UpdateSellerCommissionRate $updateSellerCommissionRate
    ) {}

    /**
     * GET /api/v1/admin/commissions?status=WITHDRAWAL_REQUESTED&seller_id=2&limit=50&offset=0
     * Lista todas las comisiones con filtros, resumen consolidado de billeteras y datos del vendedor y orden.
     */
    public function listCommissions(Request $request): void
    {
        /** @var User|null $admin */
        $admin = $request->user();
        if ($admin === null || !$admin->hasRole('admin')) {
            Response::json([], 403, 'fail', 'Acceso restringido a administradores.');
            return;
        }

        $status = $request->query('status');
        $sellerId = $request->query('seller_id') !== null ? (int)$request->query('seller_id') : null;
        $limit = max(1, min(100, (int)($request->query('limit') ?? 50)));
        $offset = max(0, (int)($request->query('offset') ?? 0));

        try {
            $result = $this->listAdminCommissions->execute(
                status: $status,
                sellerId: $sellerId,
                limit: $limit,
                offset: $offset
            );

            $dto = AdminCommissionsSummaryResponseDto::create(
                summary: $result['summary'],
                pagination: $result['pagination'],
                commissions: $result['commissions']
            );

            Response::json(
                $dto->toArray(),
                200,
                'success',
                'Listado de comisiones y balance de billeteras consultado exitosamente.'
            );
        } catch (InvalidArgumentException $e) {
            Response::json(['error' => $e->getMessage()], 422, 'fail', $e->getMessage());
        } catch (Exception $e) {
            Response::json(['error' => $e->getMessage()], 500, 'error', 'Error al consultar las comisiones.');
        }
    }

    /**
     * POST /api/v1/admin/commissions/{id}/pay
     * Liquida una comisión solicitada o pendiente registrando la referencia de pago bancaria.
     */
    public function payCommission(Request $request, string $id): void
    {
        /** @var User|null $admin */
        $admin = $request->user();
        if ($admin === null || !$admin->hasRole('admin')) {
            Response::json([], 403, 'fail', 'Acceso restringido a administradores.');
            return;
        }

        $commissionId = (int)$id;
        $reference = trim((string)($request->input('payment_reference') ?? $request->input('reference') ?? ''));

        if (empty($reference)) {
            Response::json(
                ['payment_reference' => 'La referencia bancaria de liquidación (payment_reference) es obligatoria.'],
                422,
                'fail',
                'Referencia de pago requerida.'
            );
            return;
        }

        try {
            $commission = $this->payCommissionByAdmin->execute($commissionId, $reference);

            Response::json(
                CommissionResponseDto::fromEntity($commission)->toArray(),
                200,
                'success',
                "Comisión #{$commissionId} liquidada y marcada como PAID exitosamente."
            );
        } catch (InvalidArgumentException $e) {
            Response::json(['payment_reference' => $e->getMessage()], 422, 'fail', $e->getMessage());
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() <= 499 ? $e->getCode() : 500;
            Response::json(['error' => $e->getMessage()], $code, 'fail', $e->getMessage());
        }
    }

    /**
     * PUT /api/v1/admin/sellers/{id}/commission-rate
     * Configura el porcentaje de comisión personalizado pactado con el vendedor.
     */
    public function updateCommissionRate(Request $request, string $id): void
    {
        /** @var User|null $admin */
        $admin = $request->user();
        if ($admin === null || !$admin->hasRole('admin')) {
            Response::json([], 403, 'fail', 'Acceso restringido a administradores.');
            return;
        }

        $sellerId = (int)$id;
        $rateInput = $request->input('commission_rate');

        if ($rateInput === null || !is_numeric($rateInput)) {
            Response::json(
                ['commission_rate' => 'El campo commission_rate es obligatorio y debe ser un valor numérico (ej: 12.50).'],
                422,
                'fail',
                'Porcentaje de comisión inválido.'
            );
            return;
        }

        $rate = (float)$rateInput;

        try {
            $profile = $this->updateSellerCommissionRate->execute($sellerId, $rate);

            Response::json(
                SellerProfileResponseDto::fromEntity($profile)->toArray(),
                200,
                'success',
                "Porcentaje de comisión para el vendedor #{$sellerId} actualizado a {$profile->getCommissionRate()}%."
            );
        } catch (InvalidArgumentException $e) {
            Response::json(['commission_rate' => $e->getMessage()], 422, 'fail', $e->getMessage());
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() <= 499 ? $e->getCode() : 500;
            Response::json(['error' => $e->getMessage()], $code, 'fail', $e->getMessage());
        }
    }
}
