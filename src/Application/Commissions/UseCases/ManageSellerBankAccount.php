<?php

namespace App\Application\Commissions\UseCases;

use App\Domain\Commissions\Entities\SellerProfile;
use App\Domain\Commissions\Repositories\SellerProfileRepositoryInterface;
use App\Domain\Contracts\Repositories\PlanRepositoryInterface;
use Exception;
use InvalidArgumentException;

class ManageSellerBankAccount
{
    public function __construct(
        private SellerProfileRepositoryInterface $sellerProfileRepository,
        private PlanRepositoryInterface $planRepository
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function execute(int $sellerId, array $data): SellerProfile
    {
        $bankId = isset($data['bank_id']) ? (int)$data['bank_id'] : 0;
        $accountNumber = trim((string)($data['account_number'] ?? ''));
        $accountType = strtoupper(trim((string)($data['account_type'] ?? 'CORRIENTE')));
        $holderName = trim((string)($data['account_holder_name'] ?? ''));
        $holderId = strtoupper(trim((string)($data['account_holder_id'] ?? '')));
        $phonePagoMovil = trim((string)($data['phone_pago_movil'] ?? ''));

        if ($bankId <= 0) {
            throw new InvalidArgumentException('El banco receptor (bank_id) es obligatorio.');
        }

        // Validar existencia del banco en el catálogo oficial de Sudeban
        $banks = $this->planRepository->getBanks(onlyActive: true);
        $selectedBank = null;
        foreach ($banks as $bank) {
            if ((int)$bank['id'] === $bankId) {
                $selectedBank = $bank;
                break;
            }
        }

        if ($selectedBank === null) {
            throw new Exception('El banco seleccionado no existe o se encuentra inactivo.', 404);
        }

        $cleanAccount = preg_replace('/\D/', '', $accountNumber);
        if (strlen($cleanAccount) !== 20) {
            throw new InvalidArgumentException('El número de cuenta bancaria debe tener exactamente 20 dígitos.');
        }

        // Cotejo Sudeban: los primeros 4 dígitos deben coincidir con el código del banco
        $expectedBankCode = str_pad((string)$selectedBank['code'], 4, '0', STR_PAD_LEFT);
        if (substr($cleanAccount, 0, 4) !== $expectedBankCode) {
            throw new InvalidArgumentException(sprintf(
                'Los primeros 4 dígitos de la cuenta (%s) no corresponden al código asignado por Sudeban al banco "%s" (%s).',
                substr($cleanAccount, 0, 4),
                $selectedBank['name'],
                $expectedBankCode
            ));
        }

        // Obtener o inicializar perfil de vendedor
        $profile = $this->sellerProfileRepository->findByUserId($sellerId);
        if ($profile === null) {
            $profile = new SellerProfile(
                userId: $sellerId,
                commissionRate: 10.00
            );
        }

        $profile->updateBankDetails(
            bankId: $bankId,
            accountNumber: $cleanAccount,
            accountType: $accountType,
            accountHolderName: $holderName,
            accountHolderId: $holderId,
            phonePagoMovil: $phonePagoMovil
        );

        return $this->sellerProfileRepository->save($profile);
    }
}
