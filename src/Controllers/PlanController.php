<?php

namespace App\Controllers;

use App\Application\Contracts\UseCases\ListPlans;
use App\Application\Contracts\UseCases\ManagePlan;
use App\Core\Request;
use App\Core\Response;
use App\Domain\Contracts\Repositories\PlanRepositoryInterface;
use Exception;

class PlanController
{
    public function __construct(
        private ListPlans $listPlans,
        private ManagePlan $managePlan,
        private PlanRepositoryInterface $planRepository
    ) {}

    /**
     * GET /api/v1/plans
     * Lista los planes activos con su precio en USD y conversión en tiempo real a VES
     */
    public function index(Request $request): void
    {
        try {
            $onlyActive = !filter_var($request->input('include_inactive', false), FILTER_VALIDATE_BOOLEAN);
            $result = $this->listPlans->execute($onlyActive);
            Response::json($result, 200, 'success', 'Catálogo de planes consultado exitosamente.');
        } catch (Exception $e) {
            Response::json([], 500, 'fail', $e->getMessage());
        }
    }

    /**
     * POST /api/v1/plans
     * Crea un nuevo plan de membresía (Requiere permiso manage_catalog)
     */
    public function store(Request $request): void
    {
        $name = trim((string)$request->input('name', ''));
        $priceUsd = (float)$request->input('price_usd', 0);
        $durationMonths = (int)$request->input('duration_months', 12);
        $maxServices = (int)$request->input('max_services', 2);
        $maxKm = (float)$request->input('max_km', 100.00);
        $description = $request->input('description') !== null ? (string)$request->input('description') : null;

        if ($name === '' || $priceUsd <= 0) {
            Response::json([], 422, 'fail', 'El nombre del plan y el precio en USD (mayor a 0) son obligatorios.');
            return;
        }

        try {
            $plan = $this->managePlan->create(
                name: $name,
                priceUsd: $priceUsd,
                durationMonths: $durationMonths,
                maxServices: $maxServices,
                maxKm: $maxKm,
                description: $description
            );
            Response::json($plan, 201, 'success', 'Plan creado exitosamente.');
        } catch (Exception $e) {
            $code = (is_int($e->getCode()) && $e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 422;
            Response::json([], $code, 'fail', $e->getMessage());
        }
    }

    /**
     * PUT /api/v1/plans/{id}
     * Actualiza un plan por su UUID (Requiere permiso manage_catalog)
     */
    public function update(Request $request, string $id): void
    {
        $name = trim((string)$request->input('name', ''));
        $priceUsd = (float)$request->input('price_usd', 0);
        $durationMonths = (int)$request->input('duration_months', 12);
        $maxServices = (int)$request->input('max_services', 2);
        $maxKm = (float)$request->input('max_km', 100.00);
        $description = $request->input('description') !== null ? (string)$request->input('description') : null;
        $isActive = filter_var($request->input('is_active', true), FILTER_VALIDATE_BOOLEAN);

        if ($name === '' || $priceUsd <= 0) {
            Response::json([], 422, 'fail', 'El nombre del plan y el precio en USD (mayor a 0) son obligatorios.');
            return;
        }

        try {
            $plan = $this->managePlan->update(
                uuid: $id,
                name: $name,
                priceUsd: $priceUsd,
                durationMonths: $durationMonths,
                maxServices: $maxServices,
                maxKm: $maxKm,
                description: $description,
                isActive: $isActive
            );
            Response::json($plan, 200, 'success', 'Plan actualizado exitosamente.');
        } catch (Exception $e) {
            $code = (is_int($e->getCode()) && $e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 422;
            Response::json([], $code, 'fail', $e->getMessage());
        }
    }

    /**
     * DELETE /api/v1/plans/{id}
     * Desactiva un plan por su UUID (Requiere permiso manage_catalog)
     */
    public function destroy(Request $request, string $id): void
    {
        try {
            $this->managePlan->deactivate($id);
            Response::json([], 200, 'success', 'Plan desactivado exitosamente.');
        } catch (Exception $e) {
            $code = (is_int($e->getCode()) && $e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 404;
            Response::json([], $code, 'fail', $e->getMessage());
        }
    }

    /**
     * GET /api/v1/banks?q=venez
     * Lista y filtra en Redis los bancos nacionales activos (códigos Sudeban de 4 dígitos)
     */
    public function banks(Request $request): void
    {
        $query = $request->input('q') !== null ? trim((string)$request->input('q')) : null;
        $banks = $this->planRepository->getBanks(true, $query);
        Response::json($banks, 200, 'success', 'Catálogo de bancos consultado exitosamente.');
    }
}
