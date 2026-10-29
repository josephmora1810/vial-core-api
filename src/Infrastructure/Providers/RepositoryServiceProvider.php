<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Application\Commissions\UseCases\GenerateOrderCommission;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Redis;
use App\Infrastructure\Database\Repositories\Auth\{
    MariaDbDocumentRepository,
    MariaDbPersonalAccessTokenRepository
};
use App\Infrastructure\Database\Repositories\Commissions\{
    MariaDbCommissionRepository,
    MariaDbSellerProfileRepository
};
use App\Infrastructure\Database\Repositories\Driver\MariaDbDriverShiftRepository;
use App\Infrastructure\Database\Repositories\Contracts\{
    CachedPlanRepository,
    MariaDbPlanRepository,
    MariaDbServiceContractRepository
};
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

final class RepositoryServiceProvider
{
    /**
     * @param Database $db
     * @param Redis $redis
     * @return array
     */
    public static function register(Database $db, Redis $redis): array
    {

        //Llamamos a los Repositorios de las conexiones a la bases de datos para hacer cambios en las tablas
        $getUserRepo = fn() => new MariaDbUserRepository($db->getConnection());
        $getTokenRepo = fn() => new MariaDbPersonalAccessTokenRepository($db->getConnection());
        $getDocumentRepo = fn() => new MariaDbDocumentRepository($db->getConnection());
        $getSavedAddressRepo = fn() => new MariaDbSavedAddressRepository($db->getConnection());
        $getVehicleRepo = fn() => new MariaDbVehicleRepository($db->getConnection());
        $getCatalogRepo = fn() => new CachedVehicleCatalogRepository(
            new MariaDbVehicleCatalogRepository($db->getConnection()),
            $redis
        );

        $getTowServiceRepo = fn() => new MariaDbTowServiceRepository($db->getConnection());
        $getTowServiceLogRepo = fn() => new MariaDbTowServiceLogRepository($db->getConnection());
        $getRatingRepo = fn() => new MariaDbRatingRepository($db->getConnection());

        $getBankTokenRepo = fn() => new MariaDbBankTokenRepository($db->getConnection());
        $getExchangeRateRepo = fn() => new MariaDbExchangeRateRepository($db->getConnection());
        $getPlanRepo = fn() => new CachedPlanRepository(
            new MariaDbPlanRepository($db->getConnection()),
            $redis
        );
        $getContractRepo = fn() => new MariaDbServiceContractRepository($db->getConnection());
        $getOrderRepo = fn() => new MariaDbOrderRepository($db->getConnection());
        $getPaymentRepo = fn() => new MariaDbPaymentRepository($db->getConnection());
        $getImageRepo = fn() => new CachedImageRepository(new MariaDbImageRepository($db->getConnection()),$redis);
        $getHomeGalleryRepo = fn() => new MariaDbHomeGalleryRepository($db->getConnection(), $getImageRepo());
        $getDriverShiftRepo = fn() => new MariaDbDriverShiftRepository($db->getConnection());

        $getSellerProfileRepo = fn() => new MariaDbSellerProfileRepository($db->getConnection());
        $getCommissionRepo = fn() => new MariaDbCommissionRepository($db->getConnection());
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
