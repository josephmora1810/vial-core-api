<?php

namespace App\Application\Driver\UseCases;

use App\Application\Vehicles\DTOs\VehicleResponseDto;
use App\Domain\Auth\Enums\DocumentStatus;
use App\Domain\Auth\Enums\DocumentType;
use App\Domain\Auth\Repositories\DocumentRepositoryInterface;
use App\Domain\Users\Enums\UserRole;
use App\Domain\Users\Repositories\UserRepositoryInterface;
use App\Domain\Vehicles\Enums\VehicleStatus;
use App\Domain\Vehicles\Repositories\VehicleRepositoryInterface;
use Exception;

class GetDriverFleetStatus
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private DocumentRepositoryInterface $documentRepository,
        private VehicleRepositoryInterface $vehicleRepository
    ) {}

    /**
     * Consulta el estado de verificación de flota y documentación de un operador de grúa (FR-09 y FR-10).
     *
     * @param int $driverUserId
     * @return array<string, mixed>
     */
    public function execute(int $driverUserId): array
    {
        $driver = $this->userRepository->findById($driverUserId);
        if ($driver === null) {
            throw new Exception('Operador de grúa no encontrado.', 404);
        }

        if (!$driver->hasRole(UserRole::Driver->getValue()) && !$driver->hasRole(UserRole::Admin->getValue())) {
            throw new Exception('El usuario no posee el rol de conductor u operador de grúas.', 403);
        }

        $pendingRequirements = [];

        // 1. Verificar Cédula de Identidad
        $identityDoc = $this->documentRepository->findLatestByEntity(
            'user',
            $driverUserId,
            DocumentType::IdentityCard
        );
        $identityStatus = $identityDoc?->getStatus()->getValue() ?? 'NOT_SUBMITTED';
        if ($identityStatus !== DocumentStatus::Verified->getValue()) {
            $pendingRequirements[] = match ($identityStatus) {
                'PENDING'       => 'Cédula de Identidad en revisión por el Administrador.',
                'REJECTED'      => 'Cédula de Identidad rechazada. Debe reenviarla.',
                default         => 'Debe cargar su Cédula de Identidad.'
            };
        }

        // 2. Verificar Licencia de Conducir
        $licenseDoc = $this->documentRepository->findLatestByEntity(
            'user',
            $driverUserId,
            DocumentType::DrivingLicense
        );
        $licenseStatus = $licenseDoc?->getStatus()->getValue() ?? 'NOT_SUBMITTED';
        if ($licenseStatus !== DocumentStatus::Verified->getValue()) {
            $pendingRequirements[] = match ($licenseStatus) {
                'PENDING'       => 'Licencia de Conducir en revisión por el Administrador.',
                'REJECTED'      => 'Licencia de Conducir rechazada. Debe reenviarla.',
                default         => 'Debe cargar su Licencia de Conducir (4to o 5to grado).'
            };
        }

        // 3. Consultar Grúas registradas
        $cranes = $this->vehicleRepository->findCranesByUserId($driverUserId);
        $cranesData = [];
        $hasVerifiedActiveCrane = false;

        foreach ($cranes as $crane) {
            $craneId = (int)$crane->getId();
            $carnetDoc = $this->documentRepository->findLatestByEntity(
                'vehicle',
                $craneId,
                DocumentType::VehicleRegistration
            ) ?? $this->documentRepository->findLatestByEntity(
                'vehicle',
                $craneId,
                DocumentType::NivCertificate
            );

            $carnetStatus = $carnetDoc?->getStatus()->getValue() ?? 'NOT_SUBMITTED';
            $isCraneReady = $crane->isVerified() 
                && $crane->getStatus() === VehicleStatus::Active 
                && $carnetStatus === DocumentStatus::Verified->getValue();

            if ($isCraneReady) {
                $hasVerifiedActiveCrane = true;
            }

            $cranesData[] = [
                'crane'               => VehicleResponseDto::fromEntity($crane)->toArray(),
                'carnet_status'       => $carnetStatus,
                'carnet_file_path'    => $carnetDoc?->getFilePath(),
                'is_operable'         => $isCraneReady,
            ];
        }

        if (count($cranes) === 0) {
            $pendingRequirements[] = 'No tiene ninguna unidad de grúa registrada en su flota.';
        } elseif (!$hasVerifiedActiveCrane) {
            $pendingRequirements[] = 'Ninguna de sus grúas registradas cuenta con aprobación administrativa activa.';
        }

        $canOperate = (count($pendingRequirements) === 0);

        return [
            'driver_id'             => $driverUserId,
            'driver_name'           => $driver->getFullName(),
            'can_operate'           => $canOperate,
            'is_fleet_ready'        => $canOperate,
            'identity_card_status'  => $identityStatus,
            'driving_license_status'=> $licenseStatus,
            'cranes_count'          => count($cranesData),
            'cranes'                => $cranesData,
            'pending_requirements'  => $pendingRequirements,
            'status_message'        => $canOperate
                ? 'Flota y documentación verificadas. El operador está habilitado para recibir solicitudes de rescate (SOS).'
                : 'Operador no habilitado para rescates. Existen requisitos pendientes por completar o aprobar.'
        ];
    }
}
