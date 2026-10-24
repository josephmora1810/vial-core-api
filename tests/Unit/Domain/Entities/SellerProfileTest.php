<?php

namespace Tests\Unit\Domain\Entities;

use App\Domain\Commissions\Entities\SellerProfile;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SellerProfileTest extends TestCase
{
    public function testCanInstantiateSellerProfileWithDefaults(): void
    {
        $profile = new SellerProfile(userId: 10);

        $this->assertSame(10, $profile->getUserId());
        $this->assertSame(10.00, $profile->getCommissionRate());
        $this->assertNull($profile->getBankId());
        $this->assertNull($profile->getAccountNumber());
        $this->assertSame('CORRIENTE', $profile->getAccountType());
        $this->assertNull($profile->getAccountHolderName());
        $this->assertNull($profile->getAccountHolderId());
        $this->assertNull($profile->getPhonePagoMovil());
        $this->assertFalse($profile->hasCompleteBankDetails());
    }

    public function testThrowsExceptionWhenCommissionRateIsOutOfRange(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El porcentaje de comisión debe estar comprendido entre 0.00% y 100.00%.');

        new SellerProfile(userId: 10, commissionRate: 105.00);
    }

    public function testSetCommissionRateUpdatesAndValidates(): void
    {
        $profile = new SellerProfile(userId: 10, commissionRate: 10.00);
        $profile->setCommissionRate(15.75);

        $this->assertSame(15.75, $profile->getCommissionRate());

        $this->expectException(InvalidArgumentException::class);
        $profile->setCommissionRate(-1.00);
    }

    public function testUpdateBankDetailsValidatesAccountLength(): void
    {
        $profile = new SellerProfile(userId: 10);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El número de cuenta bancaria nacional debe contener exactamente 20 dígitos numéricos.');

        $profile->updateBankDetails(
            bankId: 1,
            accountNumber: '0102123456789', // 13 dígitos
            accountType: 'CORRIENTE',
            accountHolderName: 'Inversiones XYZ',
            accountHolderId: 'J-12345678-0',
            phonePagoMovil: '04121234567'
        );
    }

    public function testUpdateBankDetailsValidatesAccountType(): void
    {
        $profile = new SellerProfile(userId: 10);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El tipo de cuenta debe ser CORRIENTE o AHORRO.');

        $profile->updateBankDetails(
            bankId: 1,
            accountNumber: '01020000000000000001',
            accountType: 'NOMINA',
            accountHolderName: 'Inversiones XYZ',
            accountHolderId: 'J-12345678-0',
            phonePagoMovil: '04121234567'
        );
    }

    public function testUpdateBankDetailsValidatesHolderId(): void
    {
        $profile = new SellerProfile(userId: 10);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El documento de identidad o RIF del titular no tiene un formato válido');

        $profile->updateBankDetails(
            bankId: 1,
            accountNumber: '01020000000000000001',
            accountType: 'CORRIENTE',
            accountHolderName: 'Inversiones XYZ',
            accountHolderId: 'INVALID_RIF',
            phonePagoMovil: '04121234567'
        );
    }

    public function testUpdateBankDetailsValidatesPhoneLength(): void
    {
        $profile = new SellerProfile(userId: 10);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El teléfono asociado a Pago Móvil debe contener entre 10 y 11 dígitos.');

        $profile->updateBankDetails(
            bankId: 1,
            accountNumber: '01020000000000000001',
            accountType: 'CORRIENTE',
            accountHolderName: 'Inversiones XYZ',
            accountHolderId: 'V-12345678',
            phonePagoMovil: '123'
        );
    }

    public function testUpdateBankDetailsSucceedsAndSetsComplete(): void
    {
        $profile = new SellerProfile(userId: 10);
        $this->assertFalse($profile->hasCompleteBankDetails());

        $profile->updateBankDetails(
            bankId: 5,
            accountNumber: '0102-0000-0000-0000-0001',
            accountType: 'ahorro',
            accountHolderName: 'Valeria Moreno',
            accountHolderId: 'V-18765432',
            phonePagoMovil: '0414-1234567'
        );

        $this->assertSame(5, $profile->getBankId());
        $this->assertSame('01020000000000000001', $profile->getAccountNumber());
        $this->assertSame('AHORRO', $profile->getAccountType());
        $this->assertSame('Valeria Moreno', $profile->getAccountHolderName());
        $this->assertSame('V-18765432', $profile->getAccountHolderId());
        $this->assertSame('04141234567', $profile->getPhonePagoMovil());
        $this->assertTrue($profile->hasCompleteBankDetails());

        $arr = $profile->toArray();
        $this->assertSame(10, $arr['user_id']);
        $this->assertSame('01020000000000000001', $arr['account_number']);
        $this->assertTrue($arr['has_bank_details']);
    }
}
