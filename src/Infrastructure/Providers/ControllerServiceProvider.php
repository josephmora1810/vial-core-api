<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Application\Auth\UseCases\{
    GetKycStatus,
    LoginUser,
    LogoutUser,
    RefreshToken,
    RegisterUser,
    ResetPassword,
    ReviewKycDocument,
    ScanIdentityCard,
    SendPasswordResetCode,
    UploadKycDocument
};
use App\Application\Commissions\UseCases\{
    ListAdminCommissions,
    ListSellerCommissions,
    ManageSellerBankAccount,
    PayCommissionByAdmin,
    RequestCommissionWithdrawal,
    UpdateSellerCommissionRate
};
use App\Application\Contracts\UseCases\{
    CancelServiceContract,
    CheckoutServiceContract,
    ExpireOverdueContracts,
    GenerateServiceContractPdf,
    ListPlans,
    ListSellerContracts,
    ListUserContractsAndOrders,
    ManagePlan,
    RedeemServiceContract,
    RenewServiceContract,
    VerifyServiceContract
};
use App\Application\Driver\UseCases\{
    EndDriverShift,
    GetDriverFleetStatus,
    RegisterDriverCrane,
    StartDriverShift,
    UpdateDriverLocation,
    UploadDriverLicense
};
use App\Application\Landing\UseCases\{
    GetActiveHomeGallery,
    ManageHomeGallery
};
use App\Application\Payments\UseCases\{
    HandleBancaribeWebhook,
    ProcessPayment
};
use App\Application\Towing\UseCases\{
    AcceptSosService,
    ArriveTowService,
    CancelTowService,
    CompleteTowService,
    FindNearbyPendingServices,
    GetTowService,
    QuoteSosAssistance,
    RateTowService,
    RequestSosAssistance,
    UpdateCraneTelemetry
};
use App\Application\Users\UseCases\{
    ActivateUser,
    CreateSavedAddress,
    DeleteSavedAddress,
    ListSavedAddresses,
    RegisterClientBySeller,
    SearchClientByNationalId,
    SuspendUser,
    UploadUserAvatar
};
use App\Application\Vehicles\UseCases\{
    DeleteVehicle,
    ListUserVehicles,
    RegisterVehicle,
    ScanVehicleRegistration
};
use App\Controllers\{
    AdminCommissionController,
    AuthController,
    BroadcastingController,
    ContractController,
    DocumentController,
    DriverController,
    HomeController,
    LandingController,
    PaymentController,
    PlanController,
    PusherWebhookController,
    SavedAddressController,
    SellerController,
    TowingController,
    UserModerationController,
    VehicleController
};
use App\Domain\Vehicles\Services\VehicleMatcherService;
use App\Infrastructure\Pdf\FpdfContractPdfGenerator;

final class ControllerServiceProvider
{
    /**
     * @param array<string, mixed> $repos
     * @param array<string, mixed> $infra
     * @return array<string, callable>
     */
    public static function register(array $repos, array $infra): array
    {
        return array_merge(
            self::authAndIdentityControllers($repos, $infra),
            self::contractAndCommercialControllers($repos, $infra),
            self::operationsAndTowingControllers($repos, $infra)
        );
    }

    /**
     * @param array<string, mixed> $repos
     * @param array<string, mixed> $infra
     * @return array<string, callable>
     */
    private static function authAndIdentityControllers(array $repos, array $infra): array
    {
        return [
            AuthController::class => fn() => new AuthController(
                new LoginUser($repos['user'](), $repos['token'](), $infra['jwt'], $infra['rate_limiter']()),
                new RegisterUser($repos['user'](), $infra['document_extractor'], $infra['jwt'], $repos['token'](), $repos['document'](), $infra['enqueue_s3']()),
                new RefreshToken($repos['token'](), $repos['user'](), $infra['jwt']),
                new LogoutUser($repos['token']()),
                new SendPasswordResetCode($repos['user'](), $infra['mailer'], $infra['redis']()),
                new ResetPassword($repos['user'](), $repos['token'](), $infra['redis']()),
                new ScanIdentityCard($infra['document_extractor']),
                $repos['image']()
            ),
            DocumentController::class => fn() => new DocumentController(
                new UploadKycDocument($repos['document'](), $repos['user'](), $infra['document_extractor'], $infra['enqueue_s3']()),
                new GetKycStatus($repos['document'](), $repos['user']()),
                new ReviewKycDocument($repos['document'](), $repos['vehicle']())
            ),
            SavedAddressController::class => fn() => new SavedAddressController(
                new CreateSavedAddress($repos['saved_address'](), $repos['user']()),
                new ListSavedAddresses($repos['saved_address'](), $repos['user']()),
                new DeleteSavedAddress($repos['saved_address']())
            ),
            UserModerationController::class => fn() => new UserModerationController(
                new SuspendUser($repos['user'](), $repos['token'](), $infra['logger']),
                new ActivateUser($repos['user'](), $infra['logger']),
                new UploadUserAvatar($repos['user'](), $infra['storage_service'](), $repos['image']())
            ),
        ];
    }

    /**
     * @param array<string, mixed> $repos
     * @param array<string, mixed> $infra
     * @return array<string, callable>
     */
    private static function contractAndCommercialControllers(array $repos, array $infra): array
    {
        return [
            HomeController::class => fn() => new HomeController(),
            PlanController::class => fn() => new PlanController(
                new ListPlans($repos['plan'](), $infra['bancaribe_exchange']($repos)),
                new ManagePlan($repos['plan'](), $infra['bancaribe_exchange']($repos)),
                $repos['plan']()
            ),
            ContractController::class => fn() => new ContractController(
                checkoutServiceContract: new CheckoutServiceContract(
                    $repos['plan'](),
                    $repos['vehicle'](),
                    $repos['contract'](),
                    $repos['order'](),
                    $infra['bancaribe_exchange']($repos),
                    $_ENV['APP_RIF'] ?? 'J502098500',
                    $_ENV['APP_TELEFONO'] ?? '04267536320',
                    $_ENV['APP_NAME'] ?? 'UbikGo'
                ),
                listContractsAndOrders: new ListUserContractsAndOrders(
                    $repos['contract'](),
                    $repos['order'](),
                    $repos['plan'](),
                    $repos['vehicle'](),
                    $infra['bancaribe_exchange']($repos)
                ),
                generateContractPdf: new GenerateServiceContractPdf(
                    $repos['contract'](),
                    $repos['plan'](),
                    $repos['user'](),
                    $repos['vehicle'](),
                    $repos['saved_address'](),
                    $repos['catalog'](),
                    new FpdfContractPdfGenerator()
                ),
                cancelContractUseCase: new CancelServiceContract(
                    $repos['contract'](),
                    $repos['plan'](),
                    $repos['vehicle']()
                ),
                expireOverdueContractsUseCase: new ExpireOverdueContracts(
                    $repos['contract']()
                ),
                renewContractUseCase: new RenewServiceContract(
                    $repos['contract'](),
                    $repos['plan'](),
                    $repos['vehicle'](),
                    $repos['order'](),
                    $infra['bancaribe_exchange']($repos),
                    $_ENV['APP_RIF'] ?? 'J502098500',
                    $_ENV['APP_TELEFONO'] ?? '04267536320',
                    $_ENV['APP_NAME'] ?? 'UbikGo'
                ),
                redeemContractUseCase: new RedeemServiceContract(
                    $repos['contract'](),
                    $repos['plan'](),
                    $repos['vehicle']()
                ),
                verifyContractUseCase: new VerifyServiceContract(
                    $repos['contract'](),
                    $repos['plan'](),
                    $repos['user'](),
                    $repos['vehicle'](),
                    $infra['redis']()
                )
            ),
            PaymentController::class => fn() => new PaymentController(
                processPayment: new ProcessPayment(
                    $repos['order'](),
                    $repos['payment'](),
                    $repos['contract'](),
                    $repos['plan'](),
                    $infra['bancaribe_payment']($repos),
                    $infra['bancaribe_exchange']($repos),
                    $repos['generate_order_commission'](),
                    $repos['tow_service'](),
                    $infra['broadcaster'](),
                    $infra['push_notification']()
                ),
                handleWebhook: new HandleBancaribeWebhook(
                    $repos['order'](),
                    $repos['payment'](),
                    $repos['contract'](),
                    $repos['plan'](),
                    !empty($_ENV['BANCARIBE_WEBHOOK_SECRET']) ? $_ENV['BANCARIBE_WEBHOOK_SECRET'] : null,
                    $repos['generate_order_commission']()
                ),
                listContractsAndOrders: new ListUserContractsAndOrders(
                    $repos['contract'](),
                    $repos['order'](),
                    $repos['plan'](),
                    $repos['vehicle'](),
                    $infra['bancaribe_exchange']($repos)
                ),
                exchangeRateService: $infra['bancaribe_exchange']($repos),
                balanceGateway: $infra['bancaribe_balance']($repos)
            ),
            SellerController::class => fn() => new SellerController(
                new SearchClientByNationalId(
                    $repos['user'](),
                    $repos['vehicle'](),
                    $repos['contract'](),
                    $repos['plan'](),
                    $infra['redis']()
                ),
                new RegisterClientBySeller(
                    $repos['user'](),
                    $infra['document_extractor'],
                    $repos['document'](),
                    $infra['mailer'],
                    $infra['redis'](),
                    $infra['enqueue_s3']()
                ),
                new RegisterVehicle($repos['vehicle'](), $repos['user'](), $repos['document'](), $repos['catalog'](), $infra['enqueue_s3']()),
                new CheckoutServiceContract(
                    $repos['plan'](),
                    $repos['vehicle'](),
                    $repos['contract'](),
                    $repos['order'](),
                    $infra['bancaribe_exchange']($repos),
                    $_ENV['APP_RIF'] ?? 'J502098500',
                    $_ENV['APP_TELEFONO'] ?? '04267536320',
                    $_ENV['APP_NAME'] ?? 'UbikGo'
                ),
                new ListSellerContracts(
                    $repos['contract'](),
                    $repos['plan'](),
                    $repos['user'](),
                    $repos['vehicle'](),
                    $repos['order']()
                ),
                new RenewServiceContract(
                    $repos['contract'](),
                    $repos['plan'](),
                    $repos['vehicle'](),
                    $repos['order'](),
                    $infra['bancaribe_exchange']($repos),
                    $_ENV['APP_RIF'] ?? 'J502098500',
                    $_ENV['APP_TELEFONO'] ?? '04267536320',
                    $_ENV['APP_NAME'] ?? 'UbikGo'
                ),
                $repos['user'](),
                new ManageSellerBankAccount($repos['seller_profile'](), $repos['plan']()),
                new ListSellerCommissions($repos['commission'](), $repos['seller_profile'](), $repos['order'](), $repos['plan']()),
                new RequestCommissionWithdrawal($repos['commission'](), $repos['seller_profile']()),
                $repos['seller_profile'](),
                $repos['plan']()
            ),
            AdminCommissionController::class => fn() => new AdminCommissionController(
                new ListAdminCommissions(
                    $repos['commission'](),
                    $repos['seller_profile'](),
                    $repos['user'](),
                    $repos['order']()
                ),
                new PayCommissionByAdmin(
                    $repos['commission'](),
                    $repos['seller_profile']()
                ),
                new UpdateSellerCommissionRate(
                    $repos['seller_profile'](),
                    $repos['user']()
                )
            ),
            LandingController::class => fn() => new LandingController(
                new GetActiveHomeGallery($repos['home_gallery']()),
                new ManageHomeGallery($repos['home_gallery'](), $infra['storage_service']())
            ),
        ];
    }

    /**
     * @param array<string, mixed> $repos
     * @param array<string, mixed> $infra
     * @return array<string, callable>
     */
    private static function operationsAndTowingControllers(array $repos, array $infra): array
    {
        return [
            VehicleController::class => fn() => new VehicleController(
                new RegisterVehicle($repos['vehicle'](), $repos['user'](), $repos['document'](), $repos['catalog'](), $infra['enqueue_s3']()),
                new ListUserVehicles($repos['vehicle'](), $repos['user']()),
                new DeleteVehicle($repos['vehicle']()),
                new ScanVehicleRegistration($infra['document_extractor'], new VehicleMatcherService($repos['catalog']())),
                $repos['catalog'](),
                $repos['vehicle']()
            ),
            DriverController::class => fn() => new DriverController(
                new RegisterDriverCrane(
                    $repos['vehicle'](),
                    $repos['user'](),
                    $repos['document'](),
                    $infra['document_extractor'],
                    $repos['catalog'](),
                    $infra['enqueue_s3']()
                ),
                new UploadDriverLicense(
                    $repos['document'](),
                    $repos['user'](),
                    $infra['enqueue_s3']()
                ),
                new GetDriverFleetStatus(
                    $repos['user'](),
                    $repos['document'](),
                    $repos['vehicle']()
                ),
                $repos['vehicle'](),
                new StartDriverShift($repos['driver_shift'](), $repos['user'](), $repos['vehicle'](), $repos['tow_service'](), $infra['redis']()),
                new EndDriverShift($repos['driver_shift'](), $repos['user'](), $repos['tow_service'](), $infra['redis']()),
                new UpdateDriverLocation($repos['driver_shift'](), $repos['user'](), $infra['redis']()),
                $repos['driver_shift']()
            ),
            TowingController::class => fn() => new TowingController(
                new RequestSosAssistance(
                    $infra['coverage_zones'](),
                    $repos['vehicle'](),
                    $infra['routing_service'](),
                    $repos['contract'](),
                    $repos['tow_service'](),
                    $repos['order'](),
                    $infra['bancaribe_exchange']($repos),
                    $repos['saved_address'](),
                    $infra['broadcaster'](),
                    $infra['push_notification']()
                ),
                new AcceptSosService($repos['tow_service'](), $infra['broadcaster'](), $repos['order']()),
                new UpdateCraneTelemetry($repos['tow_service'](), $repos['tow_service_log'](), $infra['broadcaster']()),
                new ArriveTowService($repos['tow_service'](), $infra['broadcaster']()),
                new CompleteTowService($repos['tow_service'](), $repos['contract'](), $infra['broadcaster'](), $infra['redis']()),
                new CancelTowService($repos['tow_service'](), $infra['broadcaster']()),
                new RateTowService($repos['tow_service'](), $repos['rating'](), $infra['redis']()),
                new GetTowService($repos['tow_service'](), $repos['vehicle']()),
                new FindNearbyPendingServices($repos['tow_service'](), $repos['vehicle']()),
                new QuoteSosAssistance(
                    $infra['coverage_zones'](),
                    $repos['vehicle'](),
                    $infra['routing_service'](),
                    $repos['contract'](),
                    $repos['driver_shift'](),
                    $infra['bancaribe_exchange']($repos),
                    $infra['redis'](),
                    $repos['saved_address']()
                )
            ),
            PusherWebhookController::class => fn() => new PusherWebhookController(
                appKey: $_ENV['PUSHER_APP_KEY'] ?? '',
                appSecret: $_ENV['PUSHER_APP_SECRET'] ?? '',
                logger: $infra['logger']
            ),
            BroadcastingController::class => fn() => new BroadcastingController(
                $infra['broadcaster'](),
                $repos['tow_service']()
            ),
        ];
    }
}
