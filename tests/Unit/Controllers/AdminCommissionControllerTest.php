<?php

namespace Tests\Unit\Controllers;

use App\Application\Commissions\UseCases\{
    ListAdminCommissions,
    PayCommissionByAdmin,
    UpdateSellerCommissionRate
};
use App\Controllers\AdminCommissionController;
use App\Core\{Request, Response};
use App\Domain\Auth\{
    Entities\Role,
    ValueObjects\Password
};
use App\Domain\Commissions\Entities\{Commission, SellerProfile};
use App\Domain\Commissions\Enums\CommissionStatus;
use App\Domain\Shared\ValueObjects\{Money, Uuid};
use App\Domain\Users\Entities\User;
use App\Domain\Users\ValueObjects\{BirthDate, Email, Names, NationalId, Phone, Surnames};
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class AdminCommissionControllerTest extends TestCase
{
    private ListAdminCommissions $listAdminCommissions;
    private PayCommissionByAdmin $payCommissionByAdmin;
    private UpdateSellerCommissionRate $updateSellerCommissionRate;
    private AdminCommissionController $controller;

    protected function setUp(): void
    {
        Response::$shouldExit = false;
        $_POST = [];
        $_GET = [];

        $this->listAdminCommissions = $this->createStub(ListAdminCommissions::class);
        $this->payCommissionByAdmin = $this->createStub(PayCommissionByAdmin::class);
        $this->updateSellerCommissionRate = $this->createStub(UpdateSellerCommissionRate::class);

        $this->controller = new AdminCommissionController(
            $this->listAdminCommissions,
            $this->payCommissionByAdmin,
            $this->updateSellerCommissionRate
        );
    }

    protected function tearDown(): void
    {
        Response::$shouldExit = true;
        $_POST = [];
        $_GET = [];
    }

    private function createAdminUser(): User
    {
        $user = new User(
            id: 1,
            uuid: Uuid::generate(),
            names: new Names('Admin'),
            surnames: new Surnames('Vial'),
            email: new Email('admin@ubikgo.com'),
            password: Password::fromPlainText('AdminPass123!'),
            phone: new Phone('+584120000000'),
            walletBalance: new Money(0, 'VES'),
            identificationCard: new NationalId('V-10000000'),
            birthDate: new BirthDate('1985-01-01'),
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
            consentAt: new DateTimeImmutable(),
            deletedAt: null,
            isActive: true,
            roles: [new Role(1, 'admin')]
        );
        return $user;
    }

    private function createClientUser(): User
    {
        return new User(
            id: 2,
            uuid: Uuid::generate(),
            names: new Names('Client'),
            surnames: new Surnames('Normal'),
            email: new Email('client@ubikgo.com'),
            password: Password::fromPlainText('ClientPass123!'),
            phone: new Phone('+584121111111'),
            walletBalance: new Money(0, 'VES'),
            identificationCard: new NationalId('V-20000000'),
            birthDate: new BirthDate('1990-01-01'),
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
            consentAt: new DateTimeImmutable(),
            deletedAt: null,
            isActive: true,
            roles: [new Role(2, 'client')]
        );
    }

    public function testListCommissionsRejectsNonAdmin(): void
    {
        $request = new Request();
        $request->setUser($this->createClientUser());

        ob_start();
        $this->controller->listCommissions($request);
        $output = ob_get_clean();

        $this->assertSame(403, http_response_code());
        $data = json_decode($output, true);
        $this->assertSame('fail', $data['status']);
    }

    public function testListCommissionsSucceedsForAdmin(): void
    {
        $request = new Request();
        $request->setUser($this->createAdminUser());

        $this->listAdminCommissions->method('execute')
            ->willReturn([
                'summary'     => ['total_commissions_usd' => 200.00],
                'pagination'  => ['total' => 1, 'limit' => 50, 'offset' => 0],
                'commissions' => [['id' => 1, 'amount_usd' => 20.00]],
            ]);

        ob_start();
        $this->controller->listCommissions($request);
        $output = ob_get_clean();

        $this->assertSame(200, http_response_code());
        $data = json_decode($output, true);
        $this->assertSame('success', $data['status']);
        $this->assertEquals(200.00, $data['data']['summary']['total_commissions_usd']);
    }

    public function testPayCommissionFailsWhenMissingReference(): void
    {
        $_POST = [];
        $request = new Request();
        $request->setUser($this->createAdminUser());

        ob_start();
        $this->controller->payCommission($request, '5');
        $output = ob_get_clean();

        $this->assertSame(422, http_response_code());
        $data = json_decode($output, true);
        $this->assertSame('fail', $data['status']);
    }

    public function testPayCommissionSucceeds(): void
    {
        $_POST = ['payment_reference' => 'REF-PAGO-98765'];
        $request = new Request();
        $request->setUser($this->createAdminUser());

        $commission = new Commission(
            sellerId: 10,
            orderId: 25,
            amountUsd: 25.00,
            percentage: 10.00,
            status: CommissionStatus::Paid,
            id: 5,
            paymentReference: 'REF-PAGO-98765',
            paidAt: new DateTimeImmutable('2026-06-01 12:00:00')
        );

        $this->payCommissionByAdmin->method('execute')
            ->willReturn($commission);

        ob_start();
        $this->controller->payCommission($request, '5');
        $output = ob_get_clean();

        $this->assertSame(200, http_response_code());
        $data = json_decode($output, true);
        $this->assertSame('success', $data['status']);
        $this->assertSame('REF-PAGO-98765', $data['data']['payment_reference']);
        $this->assertSame('PAID', $data['data']['status']);
    }

    public function testUpdateCommissionRateFailsWhenNotNumeric(): void
    {
        $_POST = ['commission_rate' => 'not_a_number'];
        $request = new Request();
        $request->setUser($this->createAdminUser());

        ob_start();
        $this->controller->updateCommissionRate($request, '10');
        $output = ob_get_clean();

        $this->assertSame(422, http_response_code());
        $data = json_decode($output, true);
        $this->assertSame('fail', $data['status']);
    }

    public function testUpdateCommissionRateSucceeds(): void
    {
        $_POST = ['commission_rate' => '15.50'];
        $request = new Request();
        $request->setUser($this->createAdminUser());

        $profile = new SellerProfile(userId: 10, commissionRate: 15.50);

        $this->updateSellerCommissionRate->method('execute')
            ->willReturn($profile);

        ob_start();
        $this->controller->updateCommissionRate($request, '10');
        $output = ob_get_clean();

        $this->assertSame(200, http_response_code());
        $data = json_decode($output, true);
        $this->assertSame('success', $data['status']);
        $this->assertSame(15.50, $data['data']['commission_rate']);
    }
}
