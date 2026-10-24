<?php

namespace Tests\Unit\Application\UseCases;

use App\Application\Documents\UseCases\{
    EnqueueDocumentS3Upload,
    ProcessDocumentS3UploadJob
};
use App\Application\Landing\UseCases\{
    GetActiveHomeGallery,
    ManageHomeGallery
};
use App\Application\Users\UseCases\{
    ActivateUser,
    SuspendUser,
    UploadUserAvatar
};
use App\Domain\Auth\Entities\{
    Document,
    PersonalAccessToken,
    Role
};
use App\Domain\Auth\Enums\{
    DocumentStatus,
    DocumentType
};
use App\Domain\Shared\ValueObjects\{
    Money,
    Uuid
};
use App\Domain\Auth\ValueObjects\Password;
use App\Domain\Users\{
    Entities\User,
    Enums\UserRole,
    ValueObjects\BirthDate,
    ValueObjects\Email,
    ValueObjects\Names,
    ValueObjects\NationalId,
    ValueObjects\Phone,
    ValueObjects\Surnames
};
use App\Infrastructure\Database\Redis;
use App\Infrastructure\Database\Repositories\Shared\CachedImageRepository;
use App\Infrastructure\Logging\IntegrationLogger;
use DateTimeImmutable;
use Exception;
use PHPUnit\Framework\TestCase;
use Tests\Doubles\{
    InMemoryDocumentRepository,
    InMemoryHomeGalleryRepository,
    InMemoryImageRepository,
    InMemoryPersonalAccessTokenRepository,
    InMemoryQueueService,
    InMemoryStorageService,
    InMemoryUserRepository
};

class UserModerationAndGalleryTest extends TestCase
{
    private InMemoryUserRepository $userRepository;
    private InMemoryPersonalAccessTokenRepository $tokenRepository;
    private InMemoryImageRepository $imageRepository;
    private InMemoryHomeGalleryRepository $galleryRepository;
    private InMemoryStorageService $storageService;
    private InMemoryQueueService $queueService;
    private InMemoryDocumentRepository $documentRepository;
    private IntegrationLogger $logger;
    private string $tempLogFile;
    private string $tempImageFile;

    protected function setUp(): void
    {
        $this->userRepository = new InMemoryUserRepository();
        $this->tokenRepository = new InMemoryPersonalAccessTokenRepository();
        $this->imageRepository = new InMemoryImageRepository();
        $this->galleryRepository = new InMemoryHomeGalleryRepository();
        $this->storageService = new InMemoryStorageService();
        $this->queueService = new InMemoryQueueService();
        $this->documentRepository = new InMemoryDocumentRepository();

        $this->tempLogFile = sys_get_temp_dir() . '/test_moderation_logs_' . bin2hex(random_bytes(4)) . '.jsonl';
        $this->logger = new IntegrationLogger($this->tempLogFile);

        $this->tempImageFile = sys_get_temp_dir() . '/test_avatar_' . bin2hex(random_bytes(4)) . '.jpg';
        file_put_contents($this->tempImageFile, 'FAKE_IMAGE_DATA');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempLogFile)) {
            @unlink($this->tempLogFile);
        }
        if (file_exists($this->tempImageFile)) {
            @unlink($this->tempImageFile);
        }
    }

    private function createSampleUser(int $id, string $emailStr, UserRole $role = UserRole::Client, bool $isActive = true): User
    {
        $user = new User(
            id: $id,
            uuid: Uuid::generate(),
            names: new Names('Carlos', 'Alberto'),
            surnames: new Surnames('Perez', 'Gomez'),
            email: new Email($emailStr),
            password: Password::fromPlainText('Secret123!*'),
            phone: new Phone('04121234567'),
            walletBalance: new Money(0, 'VES'),
            identificationCard: new NationalId('V-12345678'),
            birthDate: new BirthDate('1990-05-15'),
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
            consentAt: new DateTimeImmutable(),
            deletedAt: null,
            isActive: $isActive
        );
        $user->assignRole(new Role(null, $role->value));
        $this->userRepository->save($user);
        return $user;
    }

    public function testSuspendUserSuccessfullyDeactivatesAndRevokesTokens(): void
    {
        $admin = $this->createSampleUser(1, 'admin@ubikgo.com', UserRole::Admin);
        $client = $this->createSampleUser(2, 'cliente@ubikgo.com', UserRole::Client);

        // Simulamos un token de sesión activo para el cliente
        $token = PersonalAccessToken::create(
            userId: 2,
            plainToken: 'sample_token',
            expiresAt: new DateTimeImmutable('+30 days')
        );
        $this->tokenRepository->save($token);

        $suspendUseCase = new SuspendUser(
            userRepository: $this->userRepository,
            tokenRepository: $this->tokenRepository,
            logger: $this->logger
        );

        $result = $suspendUseCase->execute(
            userId: 2,
            reason: 'Violación reiterada de los términos de servicio',
            adminUserId: 1
        );

        $this->assertTrue($result['suspended']);
        $this->assertFalse($result['is_active']);
        $this->assertEquals(2, $result['user_id']);

        // Verificar que el usuario en el repositorio ahora está inactivo
        $updatedClient = $this->userRepository->findById(2);
        $this->assertNotNull($updatedClient);
        $this->assertFalse($updatedClient->isActive());

        // Verificar que los tokens del usuario fueron revocados
        $savedToken = $this->tokenRepository->findByTokenHash($token->getTokenHash());
        $this->assertNotNull($savedToken);
        $this->assertTrue($savedToken->isRevoked());
    }

    public function testSuspendUserPreventsSelfSuspension(): void
    {
        $admin = $this->createSampleUser(1, 'admin@ubikgo.com', UserRole::Admin);

        $suspendUseCase = new SuspendUser(
            userRepository: $this->userRepository,
            tokenRepository: $this->tokenRepository,
            logger: $this->logger
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Un administrador no puede suspender su propia cuenta.');

        $suspendUseCase->execute(
            userId: 1,
            reason: 'Error accidental',
            adminUserId: 1
        );
    }

    public function testSuspendUserRequiresReason(): void
    {
        $this->createSampleUser(2, 'cliente@ubikgo.com');

        $suspendUseCase = new SuspendUser(
            userRepository: $this->userRepository,
            tokenRepository: $this->tokenRepository
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Debe suministrar un motivo de suspensión o baneo para auditoría.');

        $suspendUseCase->execute(userId: 2, reason: '   ');
    }

    public function testActivateUserRestoresUserAccount(): void
    {
        $client = $this->createSampleUser(2, 'cliente@ubikgo.com', UserRole::Client, isActive: false);

        $activateUseCase = new ActivateUser(
            userRepository: $this->userRepository,
            logger: $this->logger
        );

        $result = $activateUseCase->execute(userId: 2, adminUserId: 1);

        $this->assertTrue($result['activated']);
        $this->assertTrue($result['is_active']);

        $updatedClient = $this->userRepository->findById(2);
        $this->assertNotNull($updatedClient);
        $this->assertTrue($updatedClient->isActive());
    }

    public function testActivateUserFailsIfAlreadyActive(): void
    {
        $this->createSampleUser(2, 'cliente@ubikgo.com', UserRole::Client, isActive: true);

        $activateUseCase = new ActivateUser($this->userRepository);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('El usuario ya se encuentra activo.');

        $activateUseCase->execute(userId: 2);
    }

    public function testUploadUserAvatarSavesToS3AndImageRepository(): void
    {
        $user = $this->createSampleUser(3, 'avatar.user@ubikgo.com');

        $useCase = new UploadUserAvatar(
            userRepository: $this->userRepository,
            storageService: $this->storageService,
            imageRepository: $this->imageRepository
        );

        $result = $useCase->execute(3, $this->tempImageFile);

        $this->assertArrayHasKey('avatar_url', $result);
        $this->assertStringContainsString('amazonaws.com', $result['avatar_url']);
        $this->assertEquals(3, $result['user_id']);

        // Verificar que el archivo local fue eliminado tras subirse exitosamente a S3
        $this->assertFileDoesNotExist($this->tempImageFile);

        // Verificar registro 1 a 1 en tabla polimórfica images
        $imagesList = $this->imageRepository->findByImageable('user', 3);
        $this->assertCount(1, $imagesList);
        $this->assertEquals($result['avatar_url'], $imagesList[0]['path']);
        $this->assertEquals('avatar', $imagesList[0]['type']);

        // Probar re-subida (sobrescritura sin duplicar fila 1 a 1)
        $newTempFile = sys_get_temp_dir() . '/test_new_avatar_' . bin2hex(random_bytes(4)) . '.jpg';
        file_put_contents($newTempFile, 'NEW_AVATAR_BYTES');
        $result2 = $useCase->execute(3, $newTempFile);

        $imagesAfterResubmit = $this->imageRepository->findByImageable('user', 3);
        $this->assertCount(1, $imagesAfterResubmit);
        $this->assertEquals($result2['avatar_url'], $imagesAfterResubmit[0]['path']);
    }

    public function testManageHomeGalleryAndGetActiveHomeGallery(): void
    {
        $manageGallery = new ManageHomeGallery(
            galleryRepository: $this->galleryRepository,
            storageService: $this->storageService
        );

        $getActiveGallery = new GetActiveHomeGallery(
            galleryRepository: $this->galleryRepository
        );

        // Crear Banner 1 (orden 2, activo)
        $banner1 = $manageGallery->create(
            title: 'Asistencia Vial 24/7',
            subtitle: 'La mayor red de grúas de Venezuela',
            description: 'Llegamos a tu auxilio en menos de 30 minutos.',
            buttonText: 'Conocer Planes',
            buttonLink: '/planes',
            orderIndex: 2,
            isActive: true,
            localImageFilePath: $this->tempImageFile
        );

        // Crear Banner 2 (orden 1, activo)
        $banner2 = $manageGallery->create(
            title: '¡Bienvenido a UbikGo!',
            subtitle: 'Seguridad y tranquilidad en tu viaje',
            orderIndex: 1,
            isActive: true,
            imageUrl: 'https://cdn.ubikgo.com/banner-hero.jpg'
        );

        // Crear Banner 3 (inactivo)
        $banner3 = $manageGallery->create(
            title: 'Promoción de Navidad (Pausada)',
            orderIndex: 3,
            isActive: false
        );

        // Test listAll (Admin)
        $allBanners = $manageGallery->listAll();
        $this->assertCount(3, $allBanners);

        // Test getActiveHomeGallery (Público Landing Page de Vue.js)
        $activeBanners = $getActiveGallery->execute();
        $this->assertCount(2, $activeBanners);
        // Debe ordenar por orderIndex (1 primero, luego 2)
        $this->assertEquals('¡Bienvenido a UbikGo!', $activeBanners[0]->getTitle());
        $this->assertEquals('Asistencia Vial 24/7', $activeBanners[1]->getTitle());

        // Test Update
        $updated = $manageGallery->update(
            id: $banner1->getId(),
            title: 'Asistencia Vial Premium 24/7',
            orderIndex: 0
        );
        $this->assertEquals('Asistencia Vial Premium 24/7', $updated->getTitle());
        $this->assertEquals(0, $updated->getOrderIndex());

        // Ahora Banner 1 tiene orden 0, debe quedar de primero en los activos
        $activeReordered = $getActiveGallery->execute();
        $this->assertEquals('Asistencia Vial Premium 24/7', $activeReordered[0]->getTitle());

        // Test Delete
        $deleted = $manageGallery->delete($banner3->getId());
        $this->assertTrue($deleted->deleted);
        $this->assertSame($banner3->getId(), $deleted->id);
        $this->assertCount(2, $manageGallery->listAll());
    }

    public function testProcessDocumentS3UploadJobRecordsInImagesTable(): void
    {
        $document = Document::create(
            documentableType: 'user',
            documentableId: 5,
            documentType: DocumentType::IdentityCard,
            filePath: $this->tempImageFile,
            status: DocumentStatus::Pending
        );
        $this->documentRepository->save($document);
        $docId = (int)$document->getId();

        $enqueue = new EnqueueDocumentS3Upload($this->queueService);
        $enqueue->execute(
            documentId: $docId,
            localFilePath: $this->tempImageFile,
            documentType: 'identity_card',
            documentableType: 'user',
            documentableId: 5
        );

        $job = new ProcessDocumentS3UploadJob(
            queueService: $this->queueService,
            storageService: $this->storageService,
            documentRepository: $this->documentRepository,
            logger: $this->logger,
            imageRepository: $this->imageRepository
        );

        $result = $job->execute();

        $this->assertTrue($result['processed']);
        $this->assertSame('SUCCESS', $result['status']);
        $this->assertNotNull($result['s3_url']);

        // Verificar que el archivo local temporal fue eliminado tras subida a S3
        $this->assertFileDoesNotExist($this->tempImageFile);

        // Verificar que el documento actualizó su filePath
        $updatedDoc = $this->documentRepository->findById($docId);
        $this->assertEquals($result['s3_url'], $updatedDoc->getFilePath());

        // Verificar registro 1 a 1 en tabla polimórfica images vinculado a 'document'
        $docImages = $this->imageRepository->findByImageable('document', $docId);
        $this->assertCount(1, $docImages);
        $this->assertEquals($result['s3_url'], $docImages[0]['path']);
        $this->assertEquals('identity_card', $docImages[0]['type']);
    }

    public function testCachedImageRepositoryUsesRedisAndInvalidates(): void
    {
        $storage = [];
        $redis = $this->createStub(Redis::class);
        $redis->method('execute')->willReturnCallback(function (string $cmd, ...$args) use (&$storage) {
            if ($cmd === 'GET') {
                return $storage[$args[0]] ?? null;
            }
            if ($cmd === 'SETEX') {
                $storage[$args[0]] = $args[2];
                return 'OK';
            }
            if ($cmd === 'DEL') {
                unset($storage[$args[0]]);
                return 1;
            }
            return null;
        });

        $cachedRepo = new CachedImageRepository(
            $this->imageRepository,
            $redis
        );

        // Guardar imagen en repo
        $cachedRepo->save('user', 99, 'avatar', 'https://s3.amazonaws.com/avatar99.jpg');

        // Primer llamado: Miss en Redis -> Lee de inner y puebla Redis
        $item1 = $cachedRepo->findLatestByImageable('user', 99, 'avatar');
        $this->assertNotNull($item1);
        $this->assertEquals('https://s3.amazonaws.com/avatar99.jpg', $item1['path']);
        $this->assertArrayHasKey('images:single:user:99:avatar', $storage);

        // Modificar inner repo directamente para comprobar que el 2do llamado viene de Redis
        $this->imageRepository->deleteByImageable('user', 99);

        // Segundo llamado: Hit en Redis -> Retorna el valor en caché
        $item2 = $cachedRepo->findLatestByImageable('user', 99, 'avatar');
        $this->assertNotNull($item2);
        $this->assertEquals('https://s3.amazonaws.com/avatar99.jpg', $item2['path']);

        // Guardar nueva imagen invalida la clave de Redis
        $cachedRepo->save('user', 99, 'avatar', 'https://s3.amazonaws.com/new_avatar99.jpg');
        $this->assertArrayNotHasKey('images:single:user:99:avatar', $storage);
    }
}
