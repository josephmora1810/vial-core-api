<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Middlewares\{
    AuthMiddleware,
    AuthorizeMiddleware,
    CorsMiddleware,
    EnforceJsonResponseMiddleware,
    EnsureKycVerifiedMiddleware,
    ThrottleMiddleware
};

final class MiddlewareServiceProvider
{
    /**
     * @param array<string, mixed> $repos
     * @param array<string, mixed> $infra
     * @return array{route_middlewares: array<string, callable>, global_middlewares: list<object>}
     */
    public static function register(array $repos, array $infra): array
    {
        return [
            'route_middlewares' => [
                'auth'       => fn() => new AuthMiddleware($infra['jwt'], $repos['user']()),
                'kyc'        => fn(?string $mode = null) => new EnsureKycVerifiedMiddleware($repos['document'](), $mode ?? 'full'),
                'role'       => AuthorizeMiddleware::role(...),
                'permission' => AuthorizeMiddleware::permission(...),
                'can'        => AuthorizeMiddleware::permission(...),
            ],
            'global_middlewares' => [
                new CorsMiddleware(),
                new EnforceJsonResponseMiddleware(),
                new ThrottleMiddleware($infra['redis']()),
            ],
        ];
    }
}
