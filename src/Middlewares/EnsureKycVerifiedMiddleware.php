<?php

namespace App\Middlewares;

use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Domain\Users\Entities\User;
use App\Domain\Auth\Enums\DocumentType;
use App\Domain\Auth\Repositories\DocumentRepositoryInterface;
use Closure;
use Exception;

/**
 * Centinela KYC (Requerimiento FR-10 - Bloqueo por Identidad).
 * Deniega operatividad de compra de servicios o solicitud de rescates
 * si el estado KYC del usuario es "Rechazado" o "Pendiente".
 *
 * Regla de negocio:
 *   (Carnet de Circulación || N.I.V verificado) && Cédula de Identidad verificada (Pasaporte opcional).
 */
class EnsureKycVerifiedMiddleware implements MiddlewareInterface
{
    public function __construct(
        private DocumentRepositoryInterface $documentRepository,
        private string $mode = 'full' // 'full' = identidad + vehículo; 'identity' = solo cédula/pasaporte; 'vehicle' = solo vehículo
    ) {}

    public function handle(Request $request, Closure $next)
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null || $user->getId() === null) {
            throw new Exception("Debes iniciar sesión para realizar esta acción.", 401);
        }

        // 1. Verificación de Identidad (Cédula de Identidad obligatoria o Pasaporte verificado)
        if ($this->mode === 'full' || $this->mode === 'identity') {
            $isIdentityVerified = $this->documentRepository->isUserIdentityVerified($user->getId());

            if (!$isIdentityVerified) {
                // Verificar si tiene un documento de identidad expresamente rechazado
                $rejectedIdDoc = $this->documentRepository->findLatestByEntity(
                    'user',
                    $user->getId(),
                    DocumentType::IdentityCard
                );

                if ($rejectedIdDoc !== null && $rejectedIdDoc->isRejected()) {
                    throw new Exception("Tu documento de identidad fue rechazado. Debes volver a subir una foto clara y legible de tu cédula para continuar.", 403);
                }

                throw new Exception("Tu identidad se encuentra en proceso de validación. Debes tener tu cédula de identidad aprobada por un administrador para realizar esta acción.", 403);
            }
        }

        // 2. Verificación de Vehículo (Carnet de Circulación || N.I.V verificado)
        if ($this->mode === 'full' || $this->mode === 'vehicle') {
            $hasVerifiedVehicle = $this->documentRepository->hasVerifiedVehicleDocument($user->getId());

            if (!$hasVerifiedVehicle) {
                throw new Exception("Debes tener registrado y verificado el carnet de circulación o N.I.V de tu vehículo para realizar esta acción.", 403);
            }
        }

        return $next($request);
    }
}
