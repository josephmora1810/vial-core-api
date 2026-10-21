<?php

namespace Tests\Unit\Application\UseCases;

use App\Application\Auth\UseCases\UploadKycDocument;
use App\Application\Documents\UseCases\EnqueueDocumentS3Upload;
use App\Application\Documents\UseCases\ProcessDocumentS3UploadJob;
use App\Domain\Auth\Entities\Document;
use App\Domain\Auth\Enums\DocumentStatus;
use App\Domain\Auth\Enums\DocumentType;
use App\Domain\Users\Entities\User;
use App\Domain\Users\Enums\UserRole;
use App\Domain\Users\ValueObjects\BirthDate;
use App\Domain\Users\ValueObjects\Email;
use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Users\ValueObjects\Names;
use App\Domain\Users\ValueObjects\NationalId;
use App\Domain\Auth\ValueObjects\Password;
use App\Domain\Users\ValueObjects\Phone;
use App\Domain\Users\ValueObjects\Surnames;
use App\Domain\Shared\ValueObjects\Uuid;
use App\Infrastructure\Logging\IntegrationLogger;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Doubles\InMemoryDocumentRepository;
use Tests\Doubles\InMemoryQueueService;
use Tests\Doubles\InMemoryStorageService;
use Tests\Doubles\InMemoryUserRepository;

class AwsS3DocumentUploadQueueTest extends TestCase
{
    private InMemoryQueueService $queueService;
    private InMemoryStorageService $storageService;
    private InMemoryDocumentRepository $documentRepository;
    private InMemoryUserRepository $userRepository;
    private string $tempTestFile;
    private string $tempLogFile;
    private IntegrationLogger $logger;

    protected function setUp(): void
    {
        $this->queueService = new InMemoryQueueService();
        $this->storageService = new InMemoryStorageService();
        $this->documentRepository = new InMemoryDocumentRepository();
        $this->userRepository = new InMemoryUserRepository();

        $this->tempTestFile = sys_get_temp_dir() . '/test_cedula_' . bin2hex(random_bytes(4)) . '.jpg';
        file_put_contents($this->tempTestFile, 'FAKE_IMAGE_DATA_BYTES');

        $this->tempLogFile = sys_get_temp_dir() . '/test_integrations_' . bin2hex(random_bytes(4)) . '.jsonl';
        $this->logger = new IntegrationLogger($this->tempLogFile);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempTestFile)) {
            @unlink($this->tempTestFile);
        }
        if (file_exists($this->tempLogFile)) {
            @unlink($this->tempLogFile);
        }
    }

    public function testEnqueueS3UploadPushesValidJobToRedisQueue(): void
    {
        $enqueue = new EnqueueDocumentS3Upload($this->queueService);

        $result = $enqueue->execute(
            documentId: 42,
            localFilePath: $this->tempTestFile,
            documentType: 'identity_card',
            documentableType: 'user',
            documentableId: 10
        );

        $this->assertTrue($result['enqueued']);
        $this->assertSame(EnqueueDocumentS3Upload::QUEUE_NAME, $result['queue']);
        $this->assertSame(1, $this->queueService->size(EnqueueDocumentS3Upload::QUEUE_NAME));

        $job = $this->queueService->pop(EnqueueDocumentS3Upload::QUEUE_NAME);
        $this->assertSame(42, $job['document_id']);
        $this->assertSame($this->tempTestFile, $job['local_file_path']);
        $this->assertStringContainsString('documents/user/10/identity_card_', $job['s3_key']);
        $this->assertSame(0, $job['attempts']);
    }

    public function testProcessS3UploadJobSuccessfullyUploadsAndUpdatesDocumentFilePath(): void
    {
        // 1. Crear documento previo con ruta local
        $doc = Document::create(
            documentableType: 'user',
            documentableId: 10,
            documentType: DocumentType::IdentityCard,
            filePath: $this->tempTestFile,
            status: DocumentStatus::Pending
        );
        $this->documentRepository->save($doc);
        $docId = (int)$doc->getId();

        // 2. Encolar trabajo
        $enqueue = new EnqueueDocumentS3Upload($this->queueService);
        $enqueue->execute(
            documentId: $docId,
            localFilePath: $this->tempTestFile,
            documentType: 'identity_card',
            documentableType: 'user',
            documentableId: 10
        );

        // 3. Procesar trabajo
        $processor = new ProcessDocumentS3UploadJob(
            $this->queueService,
            $this->storageService,
            $this->documentRepository,
            $this->logger
        );

        $result = $processor->execute();

        $this->assertTrue($result['processed']);
        $this->assertSame('SUCCESS', $result['status']);
        $this->assertStringStartsWith('https://ubik-go.s3.us-east-1.amazonaws.com/documents/user/10/identity_card_', $result['s3_url']);

        // 4. Verificar que la entidad en el repositorio tiene la URL de S3 actualizada
        $updatedDoc = $this->documentRepository->findById($docId);
        $this->assertNotNull($updatedDoc);
        $this->assertSame($result['s3_url'], $updatedDoc->getFilePath());
    }

    public function testProcessS3UploadJobHandlesMissingLocalFileGracefully(): void
    {
        $nonExistentFile = sys_get_temp_dir() . '/missing_file_' . bin2hex(random_bytes(4)) . '.jpg';

        $enqueue = new EnqueueDocumentS3Upload($this->queueService);
        $enqueue->execute(
            documentId: 99,
            localFilePath: $nonExistentFile,
            documentType: 'identity_card',
            documentableType: 'user',
            documentableId: 5
        );

        $processor = new ProcessDocumentS3UploadJob(
            $this->queueService,
            $this->storageService,
            $this->documentRepository,
            $this->logger
        );

        $result = $processor->execute();

        $this->assertSame('FILE_NOT_FOUND', $result['status']);
        $this->assertSame(1, $this->queueService->size(ProcessDocumentS3UploadJob::FAILED_QUEUE_NAME));

        // Verificar que quedó registrado en logs de integración
        $this->assertFileExists($this->tempLogFile);
        $logContent = file_get_contents($this->tempLogFile);
        $this->assertStringContainsString('Archivo local inaccesible', $logContent);
    }

    public function testProcessS3UploadJobRetriesOnFailureAndSendsToFailedQueueWhenExceeded(): void
    {
        $doc = Document::create(
            documentableType: 'user',
            documentableId: 1,
            documentType: DocumentType::IdentityCard,
            filePath: $this->tempTestFile,
            status: DocumentStatus::Pending
        );
        $this->documentRepository->save($doc);
        $docId = (int)$doc->getId();

        $this->storageService->shouldFail = true;
        $this->storageService->failureMessage = 'S3 503 Slow Down / Rate limit';

        $enqueue = new EnqueueDocumentS3Upload($this->queueService);
        $enqueue->execute(
            documentId: $docId,
            localFilePath: $this->tempTestFile,
            documentType: 'identity_card',
            documentableType: 'user',
            documentableId: 1
        );

        $processor = new ProcessDocumentS3UploadJob(
            $this->queueService,
            $this->storageService,
            $this->documentRepository,
            $this->logger
        );

        // Intento 1 -> Falla y reencola
        $res1 = $processor->execute();
        $this->assertSame('RETRY_QUEUED', $res1['status']);
        $this->assertSame(1, $this->queueService->size(EnqueueDocumentS3Upload::QUEUE_NAME));

        // Intento 2 -> Falla y reencola
        $res2 = $processor->execute();
        $this->assertSame('RETRY_QUEUED', $res2['status']);
        $this->assertSame(1, $this->queueService->size(EnqueueDocumentS3Upload::QUEUE_NAME));

        // Intento 3 -> Excede reintentos y pasa a cola de fallidos
        $res3 = $processor->execute();
        $this->assertSame('MAX_ATTEMPTS_EXCEEDED', $res3['status']);
        $this->assertSame(0, $this->queueService->size(EnqueueDocumentS3Upload::QUEUE_NAME));
        $this->assertSame(1, $this->queueService->size(ProcessDocumentS3UploadJob::FAILED_QUEUE_NAME));

        $failedJob = $this->queueService->pop(ProcessDocumentS3UploadJob::FAILED_QUEUE_NAME);
        $this->assertSame(3, $failedJob['attempts']);
        $this->assertSame('S3 503 Slow Down / Rate limit', $failedJob['last_error']);
    }

    public function testUploadKycDocumentAutomaticallyEnqueuesS3Job(): void
    {
        // Registrar usuario mock
        $user = new User(
            id: 1,
            uuid: Uuid::generate(),
            names: new Names('Carlos'),
            surnames: new Surnames('Pérez'),
            email: new Email('carlos.perez@ubikgo.com'),
            password: Password::fromPlainText('ClaveFuerte123*'),
            phone: new Phone('04121234567'),
            walletBalance: new Money(0, 'VES'),
            identificationCard: new NationalId('V-12345678'),
            birthDate: new BirthDate('1990-01-01'),
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
            consentAt: new DateTimeImmutable(),
            deletedAt: null,
            isActive: true,
            roles: [],
            permissions: []
        );
        $this->userRepository->save($user);

        $enqueue = new EnqueueDocumentS3Upload($this->queueService);
        $useCase = new UploadKycDocument(
            documentRepository: $this->documentRepository,
            userRepository: $this->userRepository,
            documentExtractor: null,
            enqueueS3: $enqueue
        );

        $result = $useCase->execute(
            userId: 1,
            rawDocumentType: 'identity_card',
            filePath: $this->tempTestFile
        );

        $this->assertSame(1, $result['id']);
        $this->assertSame(1, $this->queueService->size(EnqueueDocumentS3Upload::QUEUE_NAME));

        $enqueuedJob = $this->queueService->pop(EnqueueDocumentS3Upload::QUEUE_NAME);
        $this->assertSame(1, $enqueuedJob['document_id']);
        $this->assertSame($this->tempTestFile, $enqueuedJob['local_file_path']);
    }
}
