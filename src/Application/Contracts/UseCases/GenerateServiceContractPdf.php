<?php

namespace App\Application\Contracts\UseCases;

use App\Domain\Contracts\Enums\ContractStatus;
use App\Domain\Contracts\Repositories\PlanRepositoryInterface;
use App\Domain\Contracts\Repositories\ServiceContractRepositoryInterface;
use App\Domain\Contracts\Services\ContractPdfGeneratorInterface;
use App\Domain\Users\Repositories\SavedAddressRepositoryInterface;
use App\Domain\Users\Repositories\UserRepositoryInterface;
use App\Domain\Vehicles\Repositories\VehicleCatalogRepositoryInterface;
use App\Domain\Vehicles\Repositories\VehicleRepositoryInterface;
use Exception;

class GenerateServiceContractPdf
{
    public function __construct(
        private ServiceContractRepositoryInterface $contractRepository,
        private PlanRepositoryInterface $planRepository,
        private UserRepositoryInterface $userRepository,
        private VehicleRepositoryInterface $vehicleRepository,
        private SavedAddressRepositoryInterface $savedAddressRepository,
        private VehicleCatalogRepositoryInterface $catalogRepository,
        private ContractPdfGeneratorInterface $pdfGenerator
    ) {}

    /**
     * Genera el binario PDF y el nombre de archivo de un Contrato de Servicio por su UUID o ID.
     *
     * @return array{pdf_binary: string, filename: string, contract_uuid: string, status: string}
     */
    public function execute(string|int $contractIdentifier, string $baseUrl = ''): array
    {
        $identifier = trim((string)$contractIdentifier);

        // Seguridad Antifraude: En rutas públicas / generación de PDF solo permitir UUIDv7 o el código oficial de verificación (UBKMMYY-XXXX-ZZZZ)
        // Rechazar IDs numéricos autoincrementales para prevenir ataques de fuerza bruta y enumeración de contratos.
        $contract = $this->contractRepository->findByUuid($identifier)
            ?? $this->contractRepository->findByVerificationCode($identifier);

        if ($contract === null) {
            throw new Exception('El contrato solicitado no existe.', 404);
        }

        // Si el contrato estaba ACTIVE pero ya superó su fecha de vencimiento, lo pasamos a EXPIRED automáticamente
        if ($contract->getStatus() === ContractStatus::Active && !$contract->isActive()) {
            $contract->expire();
            $this->contractRepository->save($contract);
        }

        $plan = $this->planRepository->findById($contract->getPlanId());
        if ($plan === null) {
            throw new Exception('El plan asociado al contrato no fue encontrado.', 404);
        }

        $client = $this->userRepository->findById($contract->getClientId());
        if ($client === null) {
            throw new Exception('El titular del contrato no fue encontrado.', 404);
        }

        $vehicle = null;
        $vehicleClassLabel = 'AUTOMÓVIL / PARTICULAR';
        if ($contract->getVehicleId() !== null) {
            $vehicle = $this->vehicleRepository->findById($contract->getVehicleId());
            if ($vehicle !== null && $vehicle->getVehicleClassId() !== null) {
                $classes = $this->catalogRepository->getClasses();
                foreach ($classes as $cls) {
                    if ((int)($cls['id'] ?? 0) === $vehicle->getVehicleClassId()) {
                        $className = (string)($cls['name'] ?? 'PARTICULAR');
                        $subName = null;
                        if ($vehicle->getVehicleSubclassId() !== null) {
                            $subclasses = $this->catalogRepository->getSubclasses($vehicle->getVehicleClassId());
                            foreach ($subclasses as $sub) {
                                if ((int)($sub['id'] ?? 0) === $vehicle->getVehicleSubclassId()) {
                                    $subName = (string)($sub['name'] ?? '');
                                    break;
                                }
                            }
                        }
                        $vehicleClassLabel = $subName ? "{$className} / {$subName}" : $className;
                        break;
                    }
                }
            }
        }

        // Obtener dirección principal guardada del cliente si existe
        $clientAddress = null;
        $addresses = $this->savedAddressRepository->findAllByUserId($client->getId() ?? 0);
        if (!empty($addresses)) {
            $firstAddr = $addresses[0];
            $clientAddress = $firstAddr->getReference()
                ? $firstAddr->getAlias() . ' - ' . $firstAddr->getReference()
                : $firstAddr->getAlias();
        }

        // Asegurar que el contrato tenga verification_code e integrity_hash calculados y persistidos
        $contractUpdated = false;
        if ($contract->getVerificationCode() === null) {
            $contract->generateVerificationCode(
                $contract->getStartsAt() ?? $contract->getCreatedAt()
            );
            $contractUpdated = true;
        }

        $secretKey = $_ENV['APP_KEY'] ?? 'ubikgo-secret-contract-key';
        $plateNumber = $vehicle?->getPlateNumber()?->getValue();
        $expectedHash = $contract->calculateIntegrityHash($secretKey, $plateNumber);
        if ($contract->getIntegrityHash() !== $expectedHash) {
            $contract->setIntegrityHash($expectedHash);
            $contractUpdated = true;
        }

        if ($contractUpdated) {
            $this->contractRepository->save($contract);
        }

        $uuidStr = $contract->getUuid()->getValue();
        $code = $contract->getVerificationCode();
        $appUrl = !empty($_ENV['APP_URL']) ? rtrim($_ENV['APP_URL'], '/') : rtrim($baseUrl, '/');
        $officialVerificationUrl = $appUrl !== ''
            ? "{$appUrl}/api/v1/contracts/{$code}/pdf"
            : "/api/v1/contracts/{$code}/pdf";

        $sellerName = 'CANAL DIGITAL UBIKGO';
        if ($contract->getSellerId() !== null) {
            $seller = $this->userRepository->findById($contract->getSellerId());
            if ($seller !== null) {
                $sellerName = $seller->getFullName();
            }
        }

        $pdfBinary = $this->pdfGenerator->generate(
            contract: $contract,
            plan: $plan,
            client: $client,
            vehicle: $vehicle,
            downloadUrl: $officialVerificationUrl,
            clientAddress: $clientAddress,
            vehicleClassLabel: $vehicleClassLabel,
            sellerName: $sellerName
        );

        $contractNumber = str_pad((string)($contract->getId() ?? 1), 6, '0', STR_PAD_LEFT);

        return [
            'pdf_binary'    => $pdfBinary,
            'filename'      => "Contrato-UbikGo-{$contractNumber}.pdf",
            'contract_uuid' => $uuidStr,
            'status'        => $contract->getStatus()->getValue(),
        ];
    }
}
