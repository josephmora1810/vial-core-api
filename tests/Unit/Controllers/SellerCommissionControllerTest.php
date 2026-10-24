<?php

namespace Tests\Unit\Controllers;

use App\Application\Commissions\UseCases\{
    ListSellerCommissions,
    ManageSellerBankAccount,
    RequestCommissionWithdrawal
};
use App\Application\Contracts\UseCases\{
    CheckoutServiceContract,
    ListSellerContracts,
    RenewServiceContract
};
use App\Application\Users\UseCases\{
    RegisterClientBySeller,
    SearchClientByNationalId
};
use App\Application\Vehicles\UseCases\RegisterVehicle;
use App\Controllers\SellerController;
use App\Core\{Request, Response};
use App\Domain\Auth\{
    Entities\Role,
    ValueObjects\Password
};
use App\Domain\Commissions\Entities\SellerProfile;
use App\Domain\Commissions\Repositories\SellerProfileRepositoryInterface;
use App\Domain\Contracts\Repositories\PlanRepositoryInterface;
use App\Domain\Shared\ValueObjects\{Money, Uuid};
use App\Domain\Users\{
    Entities\User,
    Repositories\UserRepositoryInterface,
    ValueObjects\BirthDate,
    ValueObjects\Email,
    ValueObjects\Names,
    ValueObjects\NationalId,
    ValueObjects\Phone,
    ValueObjects\Surnames
};
use DateTimeImmutable;
use Exception;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SellerCommissionControllerTest extends TestCase
{
    private SearchClientByNationalId $searchClientByNationalId;
    private RegisterClientBySeller $registerClientBySeller;
    private RegisterVehicle $registerVehicle;
    private CheckoutServiceContract $checkoutServiceContract;
    private ListSellerContracts $listSellerContracts;
    private RenewServiceContract $renewServiceContract;
    private UserRepositoryInterface $userRepository;
    private ManageSellerBankAccount $manageSellerBankAccount;
    private ListSellerCommissions $listSellerCommissions;
    private RequestCommissionWithdrawal $requestCommissionWithdrawal;
    private SellerProfileRepositoryInterface $sellerProfileRepository;
    private PlanRepositoryInterface $planRepository;
    private SellerController $controller;

    protected function setUp(): void
    {
        Response::$shouldExit = false;
        $_POST = [];
        $_GET = [];

        $this->searchClientByNationalId = $this->createStub(SearchClientByNationalId::class);
        $this->registerClientBySeller = $this->createStub(RegisterClientBySeller::class);
        $this->registerVehicle = $this->createStub(RegisterVehicle::class);
        $this->checkoutServiceContract = $this->createStub(CheckoutServiceContract::class);
        $this->listSellerContracts = $this->createStub(ListSellerContracts::class);
        $this->renewServiceContract = $this->createStub(RenewServiceContract::class);
        $this->userRepository = $this->createStub(UserRepositoryInterface::class);
        $this->manageSellerBankAccount = $this->createStub(ManageSellerBankAccount::class);
        $this->listSellerCommissions = $this->createStub(ListSellerCommissions::class);
        $this->requestCommissionWithdrawal = $this->createStub(RequestCommissionWithdrawal::class);
        $this->sellerProfileRepository = $this->createStub(SellerProfileRepositoryInterface::class);
        $this->planRepository = $this->createStub(PlanRepositoryInterface::class);

        $this->controller = new SellerController(
            $this->searchClientByNationalId,
            $this->registerClientBySeller,
            $this->registerVehicle,
            $this->checkoutServiceContract,
            $this->listSellerContracts,
            $this->renewServiceContract,
            $this->userRepository,
            $this->manageSellerBankAccount,
            $this->listSellerCommissions,
            $this->requestCommissionWithdrawal,
            $this->sellerProfileRepository,
            $this->planRepository
        );
    }

    protected function tearDown(): void
    {
        Response::$shouldExit = true;
        $_POST = [];
        $_GET = [];
    }

    private function createSellerUser(int $id = 10): User
    {
        return new User(
            id: $id,
            uuid: Uuid::generate(),
            names: new Names('María'),
            surnames: new Surnames('Vendedora'),
            email: new Email('maria.seller@ubikgo.com'),
            password: Password::fromPlainText('VendedoraPass123!'),
            phone: new Phone('+584141234567'),
            walletBalance: new Money(0, 'VES'),
            identificationCard: new NationalId('V-18765432'),
            birthDate: new BirthDate('1992-05-15'),
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
            consentAt: new DateTimeImmutable(),
            deletedAt: null,
            isActive: true,
            roles: [new Role(3, 'seller')]
        );
    }

    public function testGetProfileUnauthorized(): void
    {
        $request = new Request();
        ob_start();
        $this->controller->getProfile($request);
        $output = ob_get_clean();

        $this->assertSame(401, http_response_code());
        $data = json_decode($output, true);
        $this->assertSame('fail', $data['status']);
    }

    public function testGetProfileDefaultWhenNoProfileInRepository(): void
    {
        $user = $this->createSellerUser(15);
        $request = new Request();
        $request->setUser($user);

        $this->sellerProfileRepository
            ->method('findByUserId')
            ->willReturn(null);

        ob_start();
        $this->controller->getProfile($request);
        $output = ob_get_clean();

        $this->assertSame(200, http_response_code());
        $data = json_decode($output, true);
        $this->assertSame('success', $data['status']);
        $this->assertSame(15, $data['data']['profile']['user_id']);
        $this->assertEquals(10.0, $data['data']['profile']['commission_rate']);
        $this->assertFalse($data['data']['profile']['has_bank_details']);
        $this->assertNull($data['data']['bank']);
    }

    public function testGetProfileWithExistingProfileAndBank(): void
    {
        $user = $this->createSellerUser(20);
        $request = new Request();
        $request->setUser($user);

        $profile = new SellerProfile(
            userId: 20,
            commissionRate: 15.50,
            bankId: 1,
            accountNumber: '01020123456789012345',
            accountType: 'CORRIENTE',
            accountHolderName: 'María Vendedora',
            accountHolderId: 'V-18765432',
            phonePagoMovil: '04141234567'
        );

        $this->sellerProfileRepository
            ->method('findByUserId')
            ->willReturn($profile);

        $this->planRepository
            ->method('getBanks')
            ->willReturn([
                ['id' => 1, 'code' => '0102', 'name' => 'Banco de Venezuela']
            ]);

        ob_start();
        $this->controller->getProfile($request);
        $output = ob_get_clean();

        $this->assertSame(200, http_response_code());
        $data = json_decode($output, true);
        $this->assertSame('success', $data['status']);
        $this->assertSame(200, http_response_code());
        $this->assertSame(20, $data['data']['profile']['user_id']);
        $this->assertEquals(15.50, $data['data']['profile']['commission_rate']);
        $this->assertTrue($data['data']['profile']['has_bank_details']);
        $this->assertSame('01020123456789012345', $data['data']['profile']['account_number']);
        $this->assertSame('Banco de Venezuela', $data['data']['bank']['name']);
    }

    public function testUpdateBankAccountUnauthorized(): void
    {
        $request = new Request();
        ob_start();
        $this->controller->updateBankAccount($request);
        $output = ob_get_clean();

        $this->assertSame(401, http_response_code());
    }

    public function testUpdateBankAccountWhenServiceNotInitialized(): void
    {
        $controller = new SellerController(
            $this->searchClientByNationalId,
            $this->registerClientBySeller,
            $this->registerVehicle,
            $this->checkoutServiceContract,
            $this->listSellerContracts,
            $this->renewServiceContract,
            $this->userRepository,
            null
        );

        $request = new Request();
        $request->setUser($this->createSellerUser());

        ob_start();
        $controller->updateBankAccount($request);
        $output = ob_get_clean();

        $this->assertSame(500, http_response_code());
        $data = json_decode($output, true);
        $this->assertSame('error', $data['status']);
    }

    public function testUpdateBankAccountValidationFailure(): void
    {
        $user = $this->createSellerUser(30);
        $request = new Request();
        $request->setUser($user);

        $this->manageSellerBankAccount
            ->method('execute')
            ->willThrowException(new InvalidArgumentException('El número de cuenta bancaria debe contener exactamente 20 dígitos'));

        ob_start();
        $this->controller->updateBankAccount($request);
        $output = ob_get_clean();

        $this->assertSame(422, http_response_code());
        $data = json_decode($output, true);
        $this->assertSame('fail', $data['status']);
        $this->assertArrayHasKey('bank_account', $data['data']);
    }

    public function testUpdateBankAccountSuccess(): void
    {
        $user = $this->createSellerUser(30);
        $_POST = [
            'bank_code' => '0102',
            'bank_name' => 'Banco de Venezuela',
            'account_number' => '01020123456789012345',
            'account_type' => 'CORRIENTE',
            'holder_name' => 'María Vendedora',
            'holder_id' => 'V-18765432',
            'phone' => '04141234567'
        ];
        $request = new Request();
        $request->setUser($user);

        $profile = new SellerProfile(
            userId: 30,
            commissionRate: 10.00,
            bankId: 1,
            accountNumber: '01020123456789012345',
            accountType: 'CORRIENTE',
            accountHolderName: 'María Vendedora',
            accountHolderId: 'V-18765432',
            phonePagoMovil: '04141234567'
        );

        $this->manageSellerBankAccount
            ->method('execute')
            ->willReturn($profile);

        ob_start();
        $this->controller->updateBankAccount($request);
        $output = ob_get_clean();

        $this->assertSame(200, http_response_code());
        $data = json_decode($output, true);
        $this->assertSame('success', $data['status']);
        $this->assertSame(30, $data['data']['user_id']);
        $this->assertTrue($data['data']['has_bank_details']);
        $this->assertSame('01020123456789012345', $data['data']['account_number']);
    }

    public function testMyCommissionsUnauthorized(): void
    {
        $request = new Request();
        ob_start();
        $this->controller->myCommissions($request);
        $output = ob_get_clean();

        $this->assertSame(401, http_response_code());
    }

    public function testMyCommissionsWhenServiceNull(): void
    {
        $controller = new SellerController(
            $this->searchClientByNationalId,
            $this->registerClientBySeller,
            $this->registerVehicle,
            $this->checkoutServiceContract,
            $this->listSellerContracts,
            $this->renewServiceContract,
            $this->userRepository,
            $this->manageSellerBankAccount,
            null
        );
        $request = new Request();
        $request->setUser($this->createSellerUser());

        ob_start();
        $controller->myCommissions($request);
        $output = ob_get_clean();

        $this->assertSame(500, http_response_code());
    }

    public function testMyCommissionsInvalidStatus(): void
    {
        $request = new Request();
        $request->setUser($this->createSellerUser());
        $_GET['status'] = 'INVALID_STATUS';

        $this->listSellerCommissions
            ->method('execute')
            ->willThrowException(new InvalidArgumentException('Estado no soportado.'));

        ob_start();
        $this->controller->myCommissions($request);
        $output = ob_get_clean();

        $this->assertSame(422, http_response_code());
        $data = json_decode($output, true);
        $this->assertSame('fail', $data['status']);
    }

    public function testMyCommissionsSuccess(): void
    {
        $request = new Request();
        $request->setUser($this->createSellerUser());

        $this->listSellerCommissions
            ->method('execute')
            ->willReturn([
                'wallet' => [
                    'pending_balance_usd' => 50.00,
                    'paid_balance_usd' => 100.00,
                    'withdrawal_requested_balance_usd' => 0.00,
                    'total_earned_usd' => 150.00,
                ],
                'seller_profile' => [
                    'commission_rate' => 10.0,
                    'has_bank_details' => true,
                ],
                'bank' => [
                    'bank_code' => '0102',
                    'bank_name' => 'Banco de Venezuela',
                    'account_number' => '01020123456789012345',
                ],
                'commissions' => [
                    [
                        'id' => 1,
                        'order_id' => 101,
                        'amount_usd' => 10.00,
                        'status' => 'PENDING',
                    ]
                ]
            ]);

        ob_start();
        $this->controller->myCommissions($request);
        $output = ob_get_clean();

        $this->assertSame(200, http_response_code());
        $data = json_decode($output, true);
        $this->assertSame('success', $data['status']);
        $this->assertEquals(50.00, $data['data']['wallet']['pending_balance_usd']);
        $this->assertCount(1, $data['data']['commissions']);
    }

    public function testWithdrawCommissionsUnauthorized(): void
    {
        $request = new Request();
        ob_start();
        $this->controller->withdrawCommissions($request);
        $output = ob_get_clean();

        $this->assertSame(401, http_response_code());
    }

    public function testWithdrawCommissionsWhenServiceNull(): void
    {
        $controller = new SellerController(
            $this->searchClientByNationalId,
            $this->registerClientBySeller,
            $this->registerVehicle,
            $this->checkoutServiceContract,
            $this->listSellerContracts,
            $this->renewServiceContract,
            $this->userRepository,
            $this->manageSellerBankAccount,
            $this->listSellerCommissions,
            null
        );
        $request = new Request();
        $request->setUser($this->createSellerUser());

        ob_start();
        $controller->withdrawCommissions($request);
        $output = ob_get_clean();

        $this->assertSame(500, http_response_code());
    }

    public function testWithdrawCommissionsFailsWhenNoPendingCommissions(): void
    {
        $request = new Request();
        $request->setUser($this->createSellerUser());

        $this->requestCommissionWithdrawal
            ->method('execute')
            ->willThrowException(new Exception('No posee comisiones pendientes de liquidación para retirar.', 422));

        ob_start();
        $this->controller->withdrawCommissions($request);
        $output = ob_get_clean();

        $this->assertSame(422, http_response_code());
        $data = json_decode($output, true);
        $this->assertSame('fail', $data['status']);
    }

    public function testWithdrawCommissionsSuccess(): void
    {
        $request = new Request();
        $request->setUser($this->createSellerUser());
        $_POST['commission_ids'] = [1, 2];

        $this->requestCommissionWithdrawal
            ->method('execute')
            ->willReturn([
                'requested_amount_usd' => 35.00,
                'commissions_count' => 2,
                'commissions' => [
                    ['id' => 1, 'amount_usd' => 15.00, 'status' => 'WITHDRAWAL_REQUESTED'],
                    ['id' => 2, 'amount_usd' => 20.00, 'status' => 'WITHDRAWAL_REQUESTED'],
                ],
                'bank_details' => [
                    'bank_code' => '0102',
                    'bank_name' => 'Banco de Venezuela',
                    'account_number' => '01020123456789012345',
                    'account_type' => 'CORRIENTE',
                    'holder_name' => 'María Vendedora',
                    'holder_id' => 'V-18765432',
                    'phone' => '04141234567',
                ],
            ]);

        ob_start();
        $this->controller->withdrawCommissions($request);
        $output = ob_get_clean();

        $this->assertSame(200, http_response_code());
        $data = json_decode($output, true);
        $this->assertSame('success', $data['status']);
        $this->assertEquals(35.00, $data['data']['requested_amount_usd']);
        $this->assertSame(2, $data['data']['commissions_count']);
        $this->assertSame('01020123456789012345', $data['data']['bank_details']['account_number']);
    }
}
