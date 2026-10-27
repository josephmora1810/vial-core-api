<?php

namespace App\Application\Users\UseCases;

use App\Application\Contracts\DTOs\ServiceContractResponseDto;
use App\Application\Users\DTOs\UserResponseDto;
use App\Application\Vehicles\DTOs\VehicleResponseDto;
use App\Domain\Contracts\{
    Enums\ContractStatus,
    Repositories\PlanRepositoryInterface,
    Repositories\ServiceContractRepositoryInterface
};
use App\Domain\Shared\Services\RedisClientInterface;
use App\Domain\Users\{
    Repositories\UserRepositoryInterface,
    ValueObjects\NationalId
};
use App\Domain\Vehicles\Repositories\VehicleRepositoryInterface;
use Exception;
use InvalidArgumentException;
use Throwable;

class SearchClientByNationalId
{
    private const CACHE_PREFIX = 'seller:client_lookup:';
    private const CACHE_TTL    = 600; // 10 minutos en Redis

    public function __construct(
        private UserRepositoryInterface $userRepository,
        private VehicleRepositoryInterface $vehicleRepository,
        private ServiceContractRepositoryInterface $contractRepository,
        private PlanRepositoryInterface $planRepository,
        private ?RedisClientInterface $redis = null
    ) {}

    /**
     * Busca un cliente por su número de Cédula de Identidad (usando caché en Redis + índice único en MariaDB),
     * devolviendo además sus vehículos registrados y sus contratos (activos o vencidos para renovación).
     *
     * @return array<string, mixed>
     */
    public function execute(string $rawNationalId): array
    {
        try {
            $nationalId = new NationalId($rawNationalId);
        } catch (InvalidArgumentException $e) {
            throw new Exception($e->getMessage(), 422);
        }

        $cacheKey = self::CACHE_PREFIX . $nationalId->getValue();
        $user = null;

        // 1. Intentar resolver ID del cliente desde Redis para acelerar lecturas repetidas
        if ($this->redis !== null) {
            try {
                $cachedUserId = $this->redis->execute('GET', $cacheKey);
                if (is_string($cachedUserId) && ctype_digit($cachedUserId)) {
                    $user = $this->userRepository->findById((int)$cachedUserId);
                }
            } catch (Throwable) {
                // Fallback transparente a MariaDB si Redis no responde
            }
        }

        // 2. Consulta indexada sobre idx_users_identification_card_unique en MariaDB
        if ($user === null) {
            $user = $this->userRepository->findByIdentificationCard($nationalId);
            if ($user !== null && $user->getId() !== null && $this->redis !== null) {
                try {
                    $this->redis->execute('SET', $cacheKey, (string)$user->getId());
                    $this->redis->execute('EXPIRE', $cacheKey, self::CACHE_TTL);
                } catch (Throwable) {
                    // Continuar sin interrumpir
                }
            }
        }

        if ($user === null || $user->getId() === null) {
            return [
                'found'     => false,
                'client'    => null,
                'vehicles'  => [],
                'contracts' => [],
            ];
        }

        // 3. Consultar los vehículos activos del cliente
        $vehicles = $this->vehicleRepository->findAllByUserId($user->getId());
        $vehicleDtos = [];
        $vehiclePlatesById = [];
        foreach ($vehicles as $vehicle) {
            $vehicleDtos[] = VehicleResponseDto::fromEntity($vehicle)->toArray();
            if ($vehicle->getId() !== null) {
                $vehiclePlatesById[$vehicle->getId()] = $vehicle->getPlateNumber()->getValue();
            }
        }

        // 4. Consultar los contratos del cliente (marcando vencidos como EXPIRED en vivo para facilitar renovaciones)
        $contracts = $this->contractRepository->findByClientId($user->getId());
        $contractDtos = [];
        foreach ($contracts as $contract) {
            if ($contract->getStatus() === ContractStatus::Active && !$contract->isActive()) {
                $contract->expire();
                $this->contractRepository->save($contract);
            }

            $plan = $this->planRepository->findById($contract->getPlanId());
            $plate = $contract->getVehicleId() !== null
                ? ($vehiclePlatesById[$contract->getVehicleId()] ?? null)
                : null;

            $dto = ServiceContractResponseDto::fromEntity($contract, $plan?->getName(), $plate)->toArray();
            $dto['pdf_download_url'] = '/api/v1/contracts/' . $contract->getUuid()->getValue() . '/pdf';
            $dto['can_renew'] = in_array($contract->getStatus(), [ContractStatus::Expired, ContractStatus::Active], true);
            $contractDtos[] = $dto;
        }

        return [
            'found'     => true,
            'client'    => UserResponseDto::fromEntity($user)->toArray(),
            'vehicles'  => $vehicleDtos,
            'contracts' => $contractDtos,
        ];
    }
}
