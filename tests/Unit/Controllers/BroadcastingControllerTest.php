<?php

namespace Tests\Unit\Controllers;

use App\Controllers\BroadcastingController;
use App\Core\Request;
use App\Core\Response;
use App\Domain\Shared\Services\BroadcasterInterface;
use App\Domain\Shared\ValueObjects\Coordinates;
use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Shared\ValueObjects\Uuid;
use App\Domain\Towing\Entities\TowService;
use App\Domain\Users\Entities\User;
use App\Domain\Users\Enums\UserRole;
use App\Domain\Users\ValueObjects\BirthDate;
use App\Domain\Users\ValueObjects\Email;
use App\Domain\Users\ValueObjects\Names;
use App\Domain\Users\ValueObjects\NationalId;
use App\Domain\Auth\ValueObjects\Password;
use App\Domain\Users\ValueObjects\Phone;
use App\Domain\Users\ValueObjects\Surnames;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Doubles\InMemoryTowServiceRepository;

class BroadcastingControllerTest extends TestCase
{
    private InMemoryTowServiceRepository $towServiceRepo;
    private BroadcasterInterface $mockBroadcaster;
    private BroadcastingController $controller;

    protected function setUp(): void
    {
        Response::$shouldExit = false;
        $this->towServiceRepo = new InMemoryTowServiceRepository();
        $this->mockBroadcaster = new class implements BroadcasterInterface {
            public function broadcast(string|array $channels, string $event, array $data): bool
            {
                return true;
            }

            public function authorizeChannel(string $channelName, string $socketId, ?array $channelData = null): array
            {
                return [
                    'auth' => "mock_key:" . hash_hmac('sha256', "{$socketId}:{$channelName}", 'mock_secret'),
                ];
            }
        };

        $this->controller = new BroadcastingController(
            $this->mockBroadcaster,
            $this->towServiceRepo
        );
    }

    protected function tearDown(): void
    {
        Response::$shouldExit = true;
    }

    private function createSampleUser(int $id, string $role): User
    {
        $user = new User(
            id: $id,
            uuid: Uuid::generate(),
            names: new Names('Test', 'User'),
            surnames: new Surnames('Driver', 'Test'),
            email: new Email("user{$id}@ubikgo.com"),
            password: Password::fromPlainText('Secret123!*'),
            phone: new Phone('04121234567'),
            walletBalance: new Money(0, 'VES'),
            identificationCard: new NationalId('V-12345678'),
            birthDate: new BirthDate('1990-01-01'),
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
            consentAt: new DateTimeImmutable(),
            deletedAt: null,
            isActive: true
        );
        $user->assignRole(new \App\Domain\Auth\Entities\Role(null, $role));
        return $user;
    }

    public function testFailsWhenNotAuthenticated(): void
    {
        $request = new Request();
        ob_start();
        $this->controller->auth($request);
        $output = ob_get_clean();

        $this->assertSame(401, http_response_code());
        $data = json_decode($output, true);
        $this->assertSame('fail', $data['status']);
    }

    public function testFailsWhenMissingChannelNameOrSocketId(): void
    {
        $user = $this->createSampleUser(1, 'driver');
        $request = new Request();
        $request->setUser($user);

        ob_start();
        $this->controller->auth($request);
        $output = ob_get_clean();

        $this->assertSame(422, http_response_code());
    }

    public function testClientCannotSubscribeToDriverRadar(): void
    {
        $client = $this->createSampleUser(2, 'client');
        $_POST['channel_name'] = 'private-cranes-radar';
        $_POST['socket_id'] = '123.456';

        $request = new Request();
        $request->setUser($client);

        ob_start();
        $this->controller->auth($request);
        $output = ob_get_clean();

        unset($_POST['channel_name'], $_POST['socket_id']);
        $this->assertSame(403, http_response_code());
        $data = json_decode($output, true);
        $this->assertSame('No tienes autorización para acceder al radar de grúas.', $data['message']);
    }

    public function testDriverCanSubscribeToDriverRadar(): void
    {
        $driver = $this->createSampleUser(3, 'driver');
        $_POST['channel_name'] = 'private-cranes-radar';
        $_POST['socket_id'] = '987.654';

        $request = new Request();
        $request->setUser($driver);

        ob_start();
        $this->controller->auth($request);
        $output = ob_get_clean();

        unset($_POST['channel_name'], $_POST['socket_id']);
        $this->assertSame(200, http_response_code());
        $data = json_decode($output, true);
        $this->assertArrayHasKey('auth', $data);
        $this->assertStringStartsWith('mock_key:', $data['auth']);
    }

    public function testClientCanSubscribeToTheirOwnTowServiceChannel(): void
    {
        $client = $this->createSampleUser(10, 'client');
        $service = TowService::create(
            clientId: 10,
            vehicleId: 1,
            origin: new Coordinates(10.49, -66.85),
            originAddress: 'Origen',
            destination: new Coordinates(10.50, -66.90),
            destinationAddress: 'Destino',
            failureDescription: 'Falla',
            estimatedDistanceKm: 15.0
        );
        $this->towServiceRepo->save($service);
        $uuid = $service->getUuid()->getValue();

        $_POST['channel_name'] = "private-tow-{$uuid}";
        $_POST['socket_id'] = '555.666';

        $request = new Request();
        $request->setUser($client);

        ob_start();
        $this->controller->auth($request);
        $output = ob_get_clean();

        unset($_POST['channel_name'], $_POST['socket_id']);
        $this->assertSame(200, http_response_code());
        $data = json_decode($output, true);
        $this->assertArrayHasKey('auth', $data);
    }

    public function testForeignUserCannotSubscribeToAnotherUsersTowServiceChannel(): void
    {
        $foreignUser = $this->createSampleUser(99, 'client');
        $service = TowService::create(
            clientId: 10,
            vehicleId: 1,
            origin: new Coordinates(10.49, -66.85),
            originAddress: 'Origen',
            destination: new Coordinates(10.50, -66.90),
            destinationAddress: 'Destino',
            failureDescription: 'Falla',
            estimatedDistanceKm: 15.0
        );
        $this->towServiceRepo->save($service);
        $uuid = $service->getUuid()->getValue();

        $_POST['channel_name'] = "private-tow-{$uuid}";
        $_POST['socket_id'] = '555.666';

        $request = new Request();
        $request->setUser($foreignUser);

        ob_start();
        $this->controller->auth($request);
        $output = ob_get_clean();

        unset($_POST['channel_name'], $_POST['socket_id']);
        $this->assertSame(403, http_response_code());
    }
}
