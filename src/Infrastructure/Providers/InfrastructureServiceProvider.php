<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Application\Documents\UseCases\{EnqueueDocumentS3Upload, ProcessDocumentS3UploadJob};
use App\Application\Towing\UseCases\ExpireOverdueSosServices;
use App\Core\JwtService;
use App\Domain\Shared\Services\{
    BroadcasterInterface,
    PushNotificationInterface,
    QueueServiceInterface,
    StorageServiceInterface
};
use App\Infrastructure\Broadcasting\PusherBroadcaster;
use App\Infrastructure\Database\{Database,Redis};
use App\Infrastructure\Http\CurlHttpClient;
use App\Infrastructure\Logging\IntegrationLogger;
use App\Infrastructure\Mail\SmtpMailer;
use App\Infrastructure\Queue\RedisQueueService;
use App\Infrastructure\Services\Bancaribe\{
    BancaribeAuthService,
    BancaribeBalanceService,
    BancaribeExchangeService,
    BancaribePaymentService
};
use App\Infrastructure\Services\LaVenezolanaDocumentExtractor;
use App\Infrastructure\Services\PusherBeamsService;
use App\Infrastructure\Services\RedisAccountRateLimiter;
use App\Infrastructure\Services\Towing\{
    InMemoryCoverageZoneRepository,
    OsrmRoutingService
};
use App\Infrastructure\Storage\AwsS3StorageService;
use LogicException;

final class InfrastructureServiceProvider
{
    /**
     * @return array<string, mixed>
     */
    public static function register(): array
    {
        // Conexion MariaDB
        $getDb = function () {
            static $pdo = null;
            if ($pdo === null) $pdo = new Database()->getConnection();
            return $pdo;
        };

        //Conexion a Redis
        $getRedis = function () {
            static $redis = null;
            if ($redis === null) $redis = new Redis();
            return $redis;
        };

        // Registro del servicio Jwt
        $jwtService = new JwtService();

        //Registro del cliente HTTP Curl
        $httpClient = new CurlHttpClient(
            timeout: (int) ($_ENV['HTTP_TIMEOUT'] ?? 30),
            connectTimeout: (int) ($_ENV['HTTP_CONNECT_TIMEOUT'] ?? 5)
        );

        //Registro del Servicio de Logs
        IntegrationLogger::registerServicePattern('osrm', ['osrm', 'project-osrm.org', ':5000']);
        IntegrationLogger::registerServicePattern('pusher', ['pusher.com', 'pusherapp.com']);
        $integrationLogger = new IntegrationLogger();

        //Registro del servicio del OCR (usa httpClient)
        $documentExtractor = new LaVenezolanaDocumentExtractor(
            httpClient: $httpClient,
            baseUrl: $_ENV['OCR_BASE_URL'] ?? 'http://localhost:8080/api/v1',
            apiKey: !empty($_ENV['OCR_API_KEY']) ? $_ENV['OCR_API_KEY'] : null
        );

        //Registro del servicio Mailer
        $mailer = new SmtpMailer(
            host: $_ENV['SMTP_HOST'] ?? 'localhost',
            port: (int)($_ENV['SMTP_PORT'] ?? 465),
            username: $_ENV['SMTP_USERNAME'] ?? '',
            password: $_ENV['SMTP_PASSWORD'] ?? '',
            fromAddress: $_ENV['MAIL_FROM_ADDRESS'] ?? 'soporte@ubikgo.com',
            fromName: $_ENV['MAIL_FROM_NAME'] ?? 'UbikGo',
            encryption: $_ENV['SMTP_ENCRYPTION'] ?? 'ssl'
        );

        //Registro del servicio del Rate Limiter con Sliding Window (usa redis)
        $getRateLimiter = fn() => new RedisAccountRateLimiter($getRedis());
        
        //Registro del Servicio de las zonas de coberturas disponibles
        $getCoverageZones = fn() => new InMemoryCoverageZoneRepository();

        //Registro del Servicio OSRM (para calculo de rutas, usa HttpClient)
        $getRoutingService = fn() => new OsrmRoutingService(
            httpClient: $httpClient,
            baseUrl: $_ENV['OSRM_BASE_URL'] ?? null
        );

        //Registro del Servicio de Pusher para Websockets (usa el HttpClient y el Logger)
        $getBroadcaster = fn() => new PusherBroadcaster(
            httpClient: $httpClient,
            appId: $_ENV['PUSHER_APP_ID'] ?? '',
            appKey: $_ENV['PUSHER_APP_KEY'] ?? '',
            appSecret: $_ENV['PUSHER_APP_SECRET'] ?? '',
            cluster: $_ENV['PUSHER_APP_CLUSTER'] ?? 'sa1',
            port: (int)($_ENV['PUSHER_PORT'] ?? 443),
            scheme: $_ENV['PUSHER_SCHEME'] ?? 'https',
            logger: $integrationLogger
        );

        // Antes de Registrar el Core Bancario, debemos verificar los booleanos de los sandbox y el entorno del sistema (los sandbox no se deben activar en produccion)
        $isSandbox = filter_var($_ENV['BANCARIBE_SANDBOX'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $isPaymentSandbox = filter_var($_ENV['BANCARIBE_PAYMENT_SANDBOX'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $appEnv = $_ENV['APP_ENV'] ?? 'local';
        $isLocalEnv = ($appEnv === 'local');

        if (!$isLocalEnv && ($isSandbox || $isPaymentSandbox)) {
            throw new LogicException(
                "Configuración inválida de seguridad: BANCARIBE_SANDBOX y BANCARIBE_PAYMENT_SANDBOX no pueden estar habilitados cuando APP_ENV no es 'local' (entorno actual: '{$appEnv}')."
            );
        }

        //Pedimos las credenciales del core bancario
        // en este caso pedimos con errores ortograficos debido a malas experiencias
        $customerKey = $_ENV['BANCARIBE_COSTUMER_KEY'] ?? $_ENV['BANCARIBE_CONSUMER_KEY'] ?? $_ENV['BANCARIBE_CUSTOMER_KEY'] ?? '';
        $customerSecret = $_ENV['BANCARIBE_COSTUMER_SECRET'] ?? $_ENV['BANCARIBE_CONSUMER_SECRET'] ?? $_ENV['BANCARIBE_CUSTOMER_SECRET'] ?? '';

        //Generamos el servicio de autenticacion del core bancario (usa HttpClient y Redis)
        /**
         * Necesita $repos['bank_token']()
         * el cual corresponde a $getBankTokenRepo = fn() => new MariaDbBankTokenRepository($getDb()); (en RepositoryServiceProvider)
         * Nosotros generamos el servicio de manera lazy, es decir, que hasta que no la necesitemos, no se llama
         * por eso no da error al necesitar $repos['bank_token'](), porque eso se llamará después
         */
        $getBancaribeAuth = fn(array $repos) => new BancaribeAuthService(
            httpClient: $httpClient,
            tokenRepository: $repos['bank_token'](),
            redis: $getRedis(),
            baseAuthUrl: $_ENV['BANCARIBE_BASE_AUTH_URL'] ?? '',
            oauth2Endpoint: $_ENV['BANCARIBE_OAUTH2_ENDPOINT'] ?? 'oauth2/token',
            customerKey: $customerKey,
            customerSecret: $customerSecret,
            sandboxMode: $isSandbox,
            isLocalEnv: $isLocalEnv
        );

        //Generamos el servicio de Tasa de Cambios Bcv (usa HttpClient, Redis y el repositorio de autenticación del core bancario)
        /**
         * Necesita $repos['exchange_rate']()
         * el cual corresponde a $getExchangeRateRepo = fn() => new MariaDbExchangeRateRepository($getDb()); (en RepositoryServiceProvider)
         * Nosotros generamos el servicio de manera lazy, es decir, que hasta que no la necesitemos, no se llama
         * por eso no da error al necesitar $repos['exchange_rate'](), porque eso se llamará después
         */
        $getExchangeService = fn(array $repos) => new BancaribeExchangeService(
            authService: $getBancaribeAuth($repos),
            httpClient: $httpClient,
            rateRepository: $repos['exchange_rate'](),
            redis: $getRedis(),
            baseUrl: $_ENV['BANCARIBE_BASE_URL'] ?? '',
            exchangeEndpoint: $_ENV['BANCARIBE_EXCHANGE_ENDPOINT'] ?? 'BCVMicro/1.0.0/consultaTasa/tasa',
            exchangeHash: $_ENV['BANCARIBE_EXCHANGE_HASH'] ?? '',
            rif: $_ENV['APP_RIF'] ?? 'J502098500',
            isLocalEnv: $isLocalEnv,
            fallbackRate: (float)($_ENV['BANCARIBE_FALLBACK_RATE'] ?? 40.00)
        );

        //Generamos el servicio de Pagos del Core Bancario
        /**
         *  Necesita:
         *      * El servicio de Autenticacion Bancario
         *      * El servicio de Tasa de Cambios Bancario
         *      * El cliente Http Curl
         */
        $getPaymentGateway = fn(array $repos) => new BancaribePaymentService(
            authService: $getBancaribeAuth($repos),
            exchangeService: $getExchangeService($repos),
            httpClient: $httpClient,
            baseUrl: $_ENV['BANCARIBE_BASE_URL'] ?? '',
            c2pEndpoint: $_ENV['BANCARIBE_C2P_ENDPOINT'] ?? 'C2P/1.0.0/registrarPagoC2pApi',
            plusEndpoint: $_ENV['BANCARIBE_PLUS_ENDPOINT'] ?? 'COP/1.0.0/consulta-operacion',
            c2pHash: $_ENV['BANCARIBE_C2P_HASH'] ?? '',
            plusHash: $_ENV['BANCARIBE_PLUS_HASH'] ?? '',
            rifComercio: $_ENV['APP_RIF'] ?? 'J502098500',
            telefonoComercio: $_ENV['APP_TELEFONO'] ?? '04267536320',
            nombreComercio: $_ENV['APP_NAME'] ?? 'UbikGo',
            sandboxMode: $isPaymentSandbox,
            isLocalEnv: $isLocalEnv
        );

        // Generamos el servicio de Consulta de Cuentas del Core Bancario
        /**
         *  Necesita:
         *      * El servicio de Autenticacion Bancario
         *      * El servicio de Tasa de Cambios Bancario
         *      * El cliente Http Curl
         *      * Servicio de Redis
         */
        $getBalanceGateway = fn(array $repos) => new BancaribeBalanceService(
            authService: $getBancaribeAuth($repos),
            httpClient: $httpClient,
            redis: $getRedis(),
            baseUrl: $_ENV['BANCARIBE_BASE_URL'] ?? '',
            checkBalanceEndpoint: $_ENV['BANCARIBE_CHECK_BALANCE_ENDPOINT'] ?? 'CtasSaldos/1.0.0/ctasConsultaSaldos',
            checkBalanceHash: $_ENV['BANCARIBE_CHECK_BALANCE_HASH'] ?? '',
            rif: $_ENV['APP_RIF'] ?? 'J502098500',
            sandboxMode: $isSandbox,
            isLocalEnv: $isLocalEnv
        );

        // Generamos el Servicio de Queue (necesita redis)
        $getQueueService = fn() => new RedisQueueService($getRedis());

        // Generamos el Servicio de Notificaciones de Pusher (necesita el cliente Http y el Servicio de Logs)
        $getPushNotification = fn() => new PusherBeamsService(
            httpClient: $httpClient,
            instanceId: $_ENV['PUSHER_BEAMS_INSTANCE_ID'] ?? '',
            secretKey: $_ENV['PUSHER_BEAMS_SECRET_KEY'] ?? '',
            logger: $integrationLogger
        );

        // Generamos el servicio de Storage para guardar documentos (necesita el servicio de Logs)
        $getStorageService = fn() => new AwsS3StorageService(logger: $integrationLogger);

        // Generamos el Servicio que Sube las fotos al Storage en cola (Necesita el servicio de queue)
        $getEnqueueS3 = fn() => new EnqueueDocumentS3Upload($getQueueService());

        // Generamos el job que se encarga de encolar las subidas de imagenes (necesita el servicio de queue, el servicio de storage y el servicio de logs)
        /**
         * Necesita $repos['document']()
         * el cual corresponde a $getDocumentRepo = fn() => new MariaDbDocumentRepository($getDb()); (en RepositoryServiceProvider)
         * Nosotros generamos el servicio de manera lazy, es decir, que hasta que no la necesitemos, no se llama
         * por eso no da error al necesitar $repos['document'](), porque eso se llamará después
         */
        /**
         * Necesita $repos['image']()
         * el cual corresponde a $getImageRepo = fn() => new CachedImageRepository(new MariaDbImageRepository($getDb()),$getRedis()); (en RepositoryServiceProvider)
         * Nosotros generamos el servicio de manera lazy, es decir, que hasta que no la necesitemos, no se llama
         * por eso no da error al necesitar $repos['image'](), porque eso se llamará después
         */
        $getProcessS3 = fn(array $repos) => new ProcessDocumentS3UploadJob(
            queueService: $getQueueService(),
            storageService: $getStorageService(),
            documentRepository: $repos['document'](),
            imageRepository: $repos['image'](),
            logger: $integrationLogger
        );

        // Retornamos todos los servicios registrados
        return [
            'db'                 => $getDb,
            'redis'              => $getRedis,
            'jwt'                => $jwtService,
            'http'               => $httpClient,
            'logger'             => $integrationLogger,
            'document_extractor' => $documentExtractor,
            'mailer'             => $mailer,
            'rate_limiter'       => $getRateLimiter,
            'coverage_zones'     => $getCoverageZones,
            'routing_service'    => $getRoutingService,
            'broadcaster'        => $getBroadcaster,
            'push_notification'  => $getPushNotification,
            'bancaribe_auth'     => $getBancaribeAuth,
            'bancaribe_exchange' => $getExchangeService,
            'bancaribe_payment'  => $getPaymentGateway,
            'bancaribe_balance'  => $getBalanceGateway,
            'queue_service'      => $getQueueService,
            'storage_service'    => $getStorageService,
            'enqueue_s3'         => $getEnqueueS3,
            'process_s3'         => $getProcessS3,
        ];
    }

    /**
     * @param array<string, mixed> $repos
     * @param array<string, mixed> $infra
     * @return array<string, mixed>
     */
    public static function createServices(array $repos, array $infra): array
    {
        return [
            BroadcasterInterface::class       => $infra['broadcaster'],                     // Servicio de Broadcast
            PushNotificationInterface::class  => $infra['push_notification'],               // Servicio de Notificacion de Pusher
            QueueServiceInterface::class      => $infra['queue_service'],                   // Servicio de Queue
            StorageServiceInterface::class    => $infra['storage_service'],                 // Servicio de Storage (s3)
            EnqueueDocumentS3Upload::class    => $infra['enqueue_s3'],                      // Servicio de Queue para encolar documentos al S3
            ProcessDocumentS3UploadJob::class => fn() => $infra['process_s3']($repos),      // Servicio de Job para subir documentos al s3
            ExpireOverdueSosServices::class   => fn() => new ExpireOverdueSosServices(      // Servicio de Expiracion de Servicios sin responder
                $repos['tow_service'](),
                $repos['order'](),
                $repos['user'](),
                $repos['contract'](),
                $infra['broadcaster']()
            ),
        ];
    }
}
