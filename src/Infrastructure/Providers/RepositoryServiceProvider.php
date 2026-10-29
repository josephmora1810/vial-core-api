<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Application\Commissions\UseCases\GenerateOrderCommission;
use App\Infrastructure\Database\Redis;
use App\Infrastructure\Database\Repositories\Auth\{
    MariaDbDocumentRepository,
    MariaDbPersonalAccessTokenRepository
};
use App\Infrastructure\Database\Repositories\Commissions\{
    MariaDbCommissionRepository,
    MariaDbSellerProfileRepository
};
use App\Infrastructure\Database\Repositories\Contracts\{
    CachedPlanRepository,
    MariaDbPlanRepository,
    MariaDbServiceContractRepository
};
use App\Infrastructure\Database\Repositories\Driver\MariaDbDriverShiftRepository;
use App\Infrastructure\Database\Repositories\Landing\MariaDbHomeGalleryRepository;
use App\Infrastructure\Database\Repositories\Payments\{
    MariaDbBankTokenRepository,
    MariaDbExchangeRateRepository,
    MariaDbOrderRepository,
    MariaDbPaymentRepository
};
use App\Infrastructure\Database\Repositories\Shared\{
    CachedImageRepository,
    MariaDbImageRepository
};
use App\Infrastructure\Database\Repositories\Towing\{
    MariaDbRatingRepository,
    MariaDbTowServiceLogRepository,
    MariaDbTowServiceRepository
};
use App\Infrastructure\Database\Repositories\Users\{
    MariaDbSavedAddressRepository,
    MariaDbUserRepository
};
use App\Infrastructure\Database\Repositories\Vehicles\{
    CachedVehicleCatalogRepository,
    MariaDbVehicleCatalogRepository,
    MariaDbVehicleRepository
};
use Closure;
use PDO;

final class RepositoryServiceProvider
{
    /**
     * @param Closure(): PDO $getDb
     * @param Closure(): Redis $getRedis
     * @return array<string, Closure>
     */
    public static function register(Closure $getDb, Closure $getRedis): array
    {
        // Llamamos a los Repositorios de las conexiones a la base de datos para hacer cambios en las tablas
        $getUserRepo = fn() => new MariaDbUserRepository($getDb());
        $getTokenRepo = fn() => new MariaDbPersonalAccessTokenRepository($getDb());
        $getDocumentRepo = fn() => new MariaDbDocumentRepository($getDb());
        $getSavedAddressRepo = fn() => new MariaDbSavedAddressRepository($getDb());
        $getVehicleRepo = fn() => new MariaDbVehicleRepository($getDb());
        $getCatalogRepo = fn() => new CachedVehicleCatalogRepository(
            new MariaDbVehicleCatalogRepository($getDb()),
            $getRedis()
        );

        $getTowServiceRepo = fn() => new MariaDbTowServiceRepository($getDb());
        $getTowServiceLogRepo = fn() => new MariaDbTowServiceLogRepository($getDb());
        $getRatingRepo = fn() => new MariaDbRatingRepository($getDb());

        $getBankTokenRepo = fn() => new MariaDbBankTokenRepository($getDb());
        $getExchangeRateRepo = fn() => new MariaDbExchangeRateRepository($getDb());
        $getPlanRepo = fn() => new CachedPlanRepository(
            new MariaDbPlanRepository($getDb()),
            $getRedis()
        );
        $getContractRepo = fn() => new MariaDbServiceContractRepository($getDb());
        $getOrderRepo = fn() => new MariaDbOrderRepository($getDb());
        $getPaymentRepo = fn() => new MariaDbPaymentRepository($getDb());
        $getImageRepo = fn() => new CachedImageRepository(
            new MariaDbImageRepository($getDb()),
            $getRedis()
        );
        $getHomeGalleryRepo = fn() => new MariaDbHomeGalleryRepository($getDb(), $getImageRepo());
        $getDriverShiftRepo = fn() => new MariaDbDriverShiftRepository($getDb());

        $getSellerProfileRepo = fn() => new MariaDbSellerProfileRepository($getDb());
        $getCommissionRepo = fn() => new MariaDbCommissionRepository($getDb());
        $getGenerateOrderCommission = fn() => new GenerateOrderCommission(
            $getCommissionRepo(),
            $getSellerProfileRepo()
        );

        return [
            'user'                       => $getUserRepo,
            'token'                      => $getTokenRepo,
            'document'                   => $getDocumentRepo,
            'saved_address'              => $getSavedAddressRepo,
            'vehicle'                    => $getVehicleRepo,
            'catalog'                    => $getCatalogRepo,
            'tow_service'                => $getTowServiceRepo,
            'tow_service_log'            => $getTowServiceLogRepo,
            'rating'                     => $getRatingRepo,
            'bank_token'                 => $getBankTokenRepo,
            'exchange_rate'              => $getExchangeRateRepo,
            'plan'                       => $getPlanRepo,
            'contract'                   => $getContractRepo,
            'order'                      => $getOrderRepo,
            'payment'                    => $getPaymentRepo,
            'image'                      => $getImageRepo,
            'home_gallery'               => $getHomeGalleryRepo,
            'driver_shift'               => $getDriverShiftRepo,
            'seller_profile'             => $getSellerProfileRepo,
            'commission'                 => $getCommissionRepo,
            'generate_order_commission'  => $getGenerateOrderCommission,
        ];
    }
}
