<?php

namespace Tests\Unit\Infrastructure;

use App\Domain\Payments\Entities\{ExchangeRate, Order, Payment};
use App\Domain\Payments\Enums\{OrderableType, OrderStatus, PaymentMethod, PaymentStatus};
use App\Domain\Payments\Services\PaymentGatewayInterface;
use App\Domain\Payments\ValueObjects\PaymentResult;
use App\Domain\Shared\Enums\Currency;
use App\Domain\Shared\ValueObjects\{Money, Uuid};
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Repositories\Payments\{
    MariaDbBankTokenRepository,
    MariaDbExchangeRateRepository,
    MariaDbOrderRepository,
    MariaDbPaymentRepository
};
use App\Infrastructure\Services\Bancaribe\{C2pStrategy, PagoMovilStrategy};
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;
use Throwable;

class PaymentInfrastructureTest extends TestCase
{
    private ?PDO $pdo = null;
    private bool $inTransaction = false;

    protected function setUp(): void
    {
        $envPath = dirname(__DIR__, 3) . '/.env';
        if (file_exists($envPath) && empty($_ENV['DB_HOST'])) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
                    continue;
                }
                [$name, $value] = explode('=', $line, 2);
                $_ENV[trim($name)] = trim($value);
            }
        }

        try {
            $this->pdo = (new Database())->getConnection();
            $this->pdo->beginTransaction();
            $this->inTransaction = true;
            $this->pdo->exec("SET FOREIGN_KEY_CHECKS=0;");
        } catch (Throwable) {
            $this->pdo = null;
        }
    }

    protected function tearDown(): void
    {
        if ($this->pdo !== null && $this->inTransaction) {
            try {
                $this->pdo->rollBack();
            } catch (Throwable) {
            }
            $this->inTransaction = false;
        }
    }

    public function testC2pStrategyDelegatesToGateway(): void
    {
        $gateway = $this->createMock(PaymentGatewayInterface::class);
        $order = new Order(
            id: 1,
            uuid: Uuid::generate(),
            orderNumber: 'ORD-001',
            userId: 5,
            orderableType: OrderableType::ServiceContract,
            orderableId: 10,
            amountUsd: new Money(10, 'USD'),
            exchangeRate: 36.5,
            amountVes: new Money(365, 'VES'),
            status: OrderStatus::Pending
        );

        $gateway->expects($this->once())
            ->method('processC2p')
            ->willReturn(new PaymentResult(true, 'Aprobado', 'TX-123'));

        $strategy = new C2pStrategy($gateway);
        $result = $strategy->process($order, ['bancoPagador' => '0114', 'otp' => '1234'], 365.0);

        $this->assertTrue($result->success);
        $this->assertSame('TX-123', $result->transactionId);
    }

    public function testPagoMovilStrategyDelegatesToGateway(): void
    {
        $gateway = $this->createMock(PaymentGatewayInterface::class);
        $order = new Order(
            id: 1,
            uuid: Uuid::generate(),
            orderNumber: 'ORD-001',
            userId: 5,
            orderableType: OrderableType::ServiceContract,
            orderableId: 10,
            amountUsd: new Money(10, 'USD'),
            exchangeRate: 36.5,
            amountVes: new Money(365, 'VES'),
            status: OrderStatus::Pending
        );

        $gateway->expects($this->once())
            ->method('verifyPagoMovil')
            ->willReturn(new PaymentResult(true, 'Aprobado PM', 'TX-PM-456'));

        $strategy = new PagoMovilStrategy($gateway);
        $result = $strategy->process($order, [
            'identificadorPersona' => 'V12345678',
            'reference'            => 'REF888',
            'date'                 => '2026-10-23',
        ], 365.0);

        $this->assertTrue($result->success);
        $this->assertSame('TX-PM-456', $result->transactionId);
    }

    public function testMariaDbOrderRepositoryCrud(): void
    {
        if ($this->pdo === null) {
            $this->markTestSkipped('MariaDB no disponible.');
        }

        $repo = new MariaDbOrderRepository($this->pdo);

        $order = new Order(
            id: null,
            uuid: Uuid::generate(),
            orderNumber: 'TEST-ORD-' . bin2hex(random_bytes(4)),
            userId: 99992,
            orderableType: OrderableType::ServiceContract,
            orderableId: 88882,
            amountUsd: new Money(50.00, 'USD'),
            exchangeRate: 36.50,
            amountVes: new Money(1825.00, 'VES'),
            status: OrderStatus::Pending
        );

        $saved = $repo->save($order);
        $this->assertNotNull($saved->getId());

        $byUuid = $repo->findByUuid($saved->getUuid()->getValue());
        $this->assertNotNull($byUuid);
        $this->assertSame($saved->getOrderNumber(), $byUuid->getOrderNumber());

        $byOrderNumber = $repo->findByOrderNumber($saved->getOrderNumber());
        $this->assertNotNull($byOrderNumber);
        $this->assertSame($saved->getId(), $byOrderNumber->getId());

        // Update
        $saved->markAsPaid('BANC-12345');
        $repo->save($saved);

        $updated = $repo->findById($saved->getId());
        $this->assertNotNull($updated);
        $this->assertTrue($updated->isPaid());
        $this->assertSame('BANC-12345', $updated->getBancaribeRef());
    }

    public function testMariaDbPaymentRepositoryCrud(): void
    {
        if ($this->pdo === null) {
            $this->markTestSkipped('MariaDB no disponible.');
        }

        $repo = new MariaDbPaymentRepository($this->pdo);

        $payment = new Payment(
            orderId: 99993,
            paidByUserId: 88883,
            amount: 500.00,
            paymentMethod: PaymentMethod::C2p,
            status: PaymentStatus::Pending,
            transactionId: 'TX-TEST-001'
        );

        $saved = $repo->save($payment);
        $this->assertNotNull($saved->getId());

        $found = $repo->findById($saved->getId());
        $this->assertNotNull($found);
        $this->assertSame('TX-TEST-001', $found->getTransactionId());

        $byOrder = $repo->findByOrderId(99993);
        $this->assertNotEmpty($byOrder);
    }

    public function testMariaDbExchangeRateRepositoryCrud(): void
    {
        if ($this->pdo === null) {
            $this->markTestSkipped('MariaDB no disponible.');
        }

        $repo = new MariaDbExchangeRateRepository($this->pdo);

        $futureDate = new DateTimeImmutable('2099-01-01');
        $rate = new ExchangeRate(
            currency: Currency::USD,
            type: 'venta',
            rate: 37.15,
            date: $futureDate
        );

        $repo->upsert($rate);

        $current = $repo->findLatest('USD', 'venta');
        $this->assertNotNull($current);
        $this->assertEqualsWithDelta(37.15, $current->getRate(), 0.01);

        $this->pdo->exec("DELETE FROM exchange_rates WHERE date = '2099-01-01'");
    }

    public function testMariaDbBankTokenRepository(): void
    {
        if ($this->pdo === null) {
            $this->markTestSkipped('MariaDB no disponible.');
        }

        $repo = new MariaDbBankTokenRepository($this->pdo);

        $tokenValue = 'fake-bancaribe-jwt-' . bin2hex(random_bytes(8));
        $expiresAt = (new DateTimeImmutable())->modify('+3600 seconds');

        $repo->upsert('bancaribe', $tokenValue, 'Bearer', $expiresAt);

        $tokenRow = $repo->findByGateway('bancaribe');
        $this->assertNotNull($tokenRow);
        $this->assertSame($tokenValue, $tokenRow['access_token']);
    }
}
