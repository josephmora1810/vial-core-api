<?php

namespace App\Controllers;

use App\Application\UseCases\CreateSavedAddress;
use App\Application\UseCases\DeleteSavedAddress;
use App\Application\UseCases\ListSavedAddresses;
use App\Core\Request;
use App\Core\Response;
use App\Domain\Entities\User;
use Exception;

class SavedAddressController
{
    public function __construct(
        private CreateSavedAddress $createSavedAddress,
        private ListSavedAddresses $listSavedAddresses,
        private DeleteSavedAddress $deleteSavedAddress
    ) {}

    /**
     * GET /api/v1/addresses
     * Lista todas las direcciones guardadas del usuario autenticado
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
            $addresses = $this->listSavedAddresses->execute($user->getId());
            Response::json($addresses, 200, 'success', 'Direcciones consultadas con éxito.');
        } catch (Exception $e) {
            Response::json([], 400, 'fail', $e->getMessage());
        }
    }

    /**
     * POST /api/v1/addresses
     * Registra una nueva dirección favorita con coordenadas GPS
     */
    public function store(Request $request): void
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($user === null || $user->getId() === null) {
            Response::json([], 401, 'fail', 'No autorizado.');
            return;
        }

        $label = (string)$request->input('label', '');
        $addressLine = (string)$request->input('address_line', '');
        $latitude = $request->input('latitude');
        $longitude = $request->input('longitude');
        $isFavorite = (bool)$request->input('is_favorite', false);

        if (empty($label) || empty($addressLine) || $latitude === null || $longitude === null) {
            Response::json([
                'label'        => empty($label) ? 'La etiqueta es requerida (ej: Casa, Taller).' : null,
                'address_line' => empty($addressLine) ? 'La dirección textual es requerida.' : null,
                'latitude'     => $latitude === null ? 'La latitud es requerida.' : null,
                'longitude'    => $longitude === null ? 'La longitud es requerida.' : null,
            ], 422, 'fail', 'Datos de validación incompletos.');
            return;
        }

        try {
            $created = $this->createSavedAddress->execute(
                userId: $user->getId(),
                label: $label,
                addressLine: $addressLine,
                latitude: (float)$latitude,
                longitude: (float)$longitude,
                isFavorite: $isFavorite
            );

            Response::json($created, 201, 'success', 'Dirección guardada exitosamente.');
        } catch (Exception $e) {
            $code = $e->getCode();
            $httpCode = (is_int($code) && $code >= 400 && $code < 600) ? $code : 400;
            Response::json([], $httpCode, 'fail', $e->getMessage());
        }
    }

    /**
     * DELETE /api/v1/addresses/{id}
     * Elimina una dirección favorita por su UUID
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
            $this->deleteSavedAddress->execute($id, $user->getId());
            Response::json([], 200, 'success', 'Dirección eliminada correctamente.');
        } catch (Exception $e) {
            $code = $e->getCode();
            $httpCode = (is_int($code) && $code >= 400 && $code < 600) ? $code : 400;
            Response::json([], $httpCode, 'fail', $e->getMessage());
        }
    }
}
