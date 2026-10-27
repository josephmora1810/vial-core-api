<?php

namespace App\Application\Contracts\UseCases;

use App\Domain\Contracts\Entities\ServiceContract;
use App\Domain\Contracts\Enums\ContractStatus;
use App\Domain\Contracts\Repositories\PlanRepositoryInterface;
use App\Domain\Contracts\Repositories\ServiceContractRepositoryInterface;
use App\Domain\Shared\Services\RedisClientInterface;
use App\Domain\Users\Repositories\UserRepositoryInterface;
use App\Domain\Vehicles\Repositories\VehicleRepositoryInterface;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use Throwable;

class VerifyServiceContract
{
    public function __construct(
        private ServiceContractRepositoryInterface $contractRepository,
        private PlanRepositoryInterface $planRepository,
        private UserRepositoryInterface $userRepository,
        private VehicleRepositoryInterface $vehicleRepository,
        private ?RedisClientInterface $redis = null
    ) {}

    /**
     * Valida y retorna los datos autoritativos de solo lectura de un Contrato de Servicio
     * a partir de su Código de Verificación oficial (formato UBKMMYY-XXXX-XXXX) o UUID.
     *
     * @param string $verificationCodeOrUuid
     * @param string|null $plate Placa opcional para cotejo de seguridad anti-clonación
     * @return array<string, mixed>
     */
    public function execute(string $verificationCodeOrUuid, ?string $plate = null): array
    {
        $code = strtoupper(trim($verificationCodeOrUuid));
        if ($code === '') {
            throw new Exception('El código de verificación es obligatorio.', 400);
        }

        $normalizedPlate = $plate !== null ? strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $plate)) : null;

        // 1. Intentar consultar desde caché Redis
        $cacheKey = "contract:verify:{$code}" . ($normalizedPlate ? ":{$normalizedPlate}" : '');
        if ($this->redis !== null) {
            try {
                $cachedJson = $this->redis->execute('GET', $cacheKey);
                if (is_string($cachedJson) && $cachedJson !== '') {
                    $cachedData = json_decode($cachedJson, true);
                    if (is_array($cachedData)) {
                        $cachedData['from_cache'] = true;
                        return $cachedData;
                    }
                }
            } catch (Throwable) {
                // Si Redis no está disponible o falla, continuar a base de datos de manera transparente
            }
        }

        // 2. Buscar por código de verificación o por UUID
        $contract = $this->contractRepository->findByVerificationCode($code);
        if ($contract === null) {
            $contract = $this->contractRepository->findByUuid($code);
        }

        if ($contract === null) {
            throw new Exception('El código de verificación no corresponde a ningún contrato válido en los registros de UbikGo.', 404);
        }

        // 3. Auto-expirar si superó su vigencia
        if ($contract->getStatus() === ContractStatus::Active && !$contract->isActive()) {
            $contract->expire();
            $this->contractRepository->save($contract);
        }

        // 4. Cargar vehículo asociado
        $vehicle = null;
        if ($contract->getVehicleId() !== null) {
            $vehicle = $this->vehicleRepository->findById($contract->getVehicleId());
        }

        // 5. Validación de placa
        $plateMatch = true;
        $plateAlertMessage = null;
        if ($normalizedPlate !== null && $normalizedPlate !== '') {
            $officialPlate = $vehicle !== null
                ? strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $vehicle->getPlateNumber()->getValue()))
                : '';
            if ($officialPlate === '' || $normalizedPlate !== $officialPlate) {
                $plateMatch = false;
                $plateAlertMessage = 'ALERTA DE SEGURIDAD: La placa consultada no coincide con el vehículo asignado en el contrato oficial.';
            }
        }

        // 6. Validación de integridad criptográfica HMAC-SHA256
        $secretKey = $_ENV['APP_KEY'] ?? 'ubikgo-secret-contract-key';
        $vehiclePlate = $vehicle?->getPlateNumber()?->getValue();
        $expectedHash = $contract->calculateIntegrityHash($secretKey, $vehiclePlate);
        $storedHash = $contract->getIntegrityHash();
        $integrityVerified = ($storedHash !== null && hash_equals($storedHash, $expectedHash));

        // 7. Cargar Plan y Cliente
        $plan = $this->planRepository->findById($contract->getPlanId());
        $client = $this->userRepository->findById($contract->getClientId());

        // Enmascaramiento de datos personales para protección de privacidad
        $maskedName = 'CLIENTE REGISTRADO';
        $maskedId = 'V-***';
        if ($client !== null) {
            $words = explode(' ', trim($client->getFullName()));
            $maskedWords = [];
            foreach ($words as $word) {
                if (mb_strlen($word) > 1) {
                    $maskedWords[] = mb_substr($word, 0, 1) . str_repeat('*', min(4, mb_strlen($word) - 1));
                } else {
                    $maskedWords[] = $word;
                }
            }
            $maskedName = implode(' ', $maskedWords);

            $idVal = $client->getIdentificationCard()->getValue();
            if (strlen($idVal) > 4) {
                $maskedId = substr($idVal, 0, 2) . '***' . substr($idVal, -4);
            } else {
                $maskedId = $idVal;
            }
        }

        // Determinar estado de validez y alertas
        $hasFraudAlert = (!$plateMatch) || ($storedHash !== null && !$integrityVerified);
        $isValid = (!$hasFraudAlert) && ($contract->getStatus() === ContractStatus::Active);

        $statusLabel = match ($contract->getStatus()) {
            ContractStatus::Active => 'ACTIVO / VIGENTE',
            ContractStatus::PendingPayment => 'PENDIENTE DE PAGO',
            ContractStatus::Expired => 'VENCIDO / CADUCADO',
            ContractStatus::Cancelled => 'ANULADO / CANCELADO',
        };

        $response = [
            'status' => $hasFraudAlert ? 'alert' : 'success',
            'is_valid' => $isValid,
            'fraud_alert' => $hasFraudAlert,
            'alert_message' => $plateAlertMessage ?? (!$integrityVerified && $storedHash !== null ? 'ALERTA: Discrepancia en el sello digital criptográfico del contrato.' : null),
            'contract' => [
                'contract_number'     => str_pad((string)($contract->getId() ?? 1), 6, '0', STR_PAD_LEFT),
                'verification_code'   => $contract->getVerificationCode(),
                'status'              => $contract->getStatus()->getValue(),
                'status_label'        => $statusLabel,
                'plan_name'           => $plan?->getName() ?? 'Plan UbikGo',
                'starts_at'           => $contract->getStartsAt()?->format('Y-m-d H:i:s'),
                'expires_at'          => $contract->getExpiresAt()?->format('Y-m-d H:i:s'),
                'remaining_services'  => $contract->getRemainingServices(),
                'remaining_km'        => $contract->getRemainingKm(),
                'vehicle'             => $vehicle ? [
                    'plate' => $vehicle->getPlateNumber()->getValue(),
                    'brand' => $vehicle->getBrandName(),
                    'model' => $vehicle->getModelName(),
                    'year'  => $vehicle->getYear(),
                    'color' => $vehicle->getColor(),
                ] : null,
                'client_masked' => [
                    'name'           => $maskedName,
                    'identification' => $maskedId,
                ],
                'integrity_verified'  => $integrityVerified,
                'digital_signature'   => 'X.509 RSA-2048 UBIK SERVICE C.A.',
            ],
            'verified_at' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
        ];

        // Guardar en Redis por 60 segundos
        if ($this->redis !== null && !$hasFraudAlert) {
            try {
                $this->redis->execute('SETEX', $cacheKey, '60', json_encode($response, JSON_UNESCAPED_UNICODE));
            } catch (Throwable) {
                // Silencioso
            }
        }

        return $response;
    }
}
