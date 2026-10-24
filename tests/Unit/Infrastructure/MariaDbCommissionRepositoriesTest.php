<?php

namespace Tests\Unit\Infrastructure;

use App\Domain\Commissions\Entities\{Commission, SellerProfile};
use App\Domain\Commissions\Enums\CommissionStatus;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Repositories\Commissions\{
    MariaDbCommissionRepository,
    MariaDbSellerProfileRepository
};
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;
use Throwable;

class MariaDbCommissionRepositoriesTest extends TestCase
{
    private ?PDO $pdo = null;
    private ?MariaDbCommissionRepository $commissionRepo = null;
    private ?MariaDbSellerProfileRepository $sellerProfileRepo = null;
    private bool $inTransaction = false;

    protected function setUp(): void
    {
        // Load .env if not loaded
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
            $this->commissionRepo = new MariaDbCommissionRepository($this->pdo);
            $this->sellerProfileRepo = new MariaDbSellerProfileRepository($this->pdo);
        } catch (Throwable $e) {
            $this->markTestSkipped('Database connection unavailable for integration testing: ' . $e->getMessage());
        }
    }

    protected function tearDown(): void
    {
        if ($this->pdo !== null && $this->inTransaction) {
            try {
                $this->pdo->rollBack();
            } catch (Throwable) {
                // Ignore rollback failure
            }
            $this->inTransaction = false;
        }
    }

    private function getOrCreateTestSellerId(): int
    {
        $stmt = $this->pdo->query("SELECT id FROM users LIMIT 1");
        $id = $stmt->fetchColumn();
        if ($id) {
            return (int)$id;
        }

        $this->pdo->exec("
            INSERT INTO users (uuid, names, surnames, email, phone, wallet_balance, identification_card, birth_date, is_active, created_at)
            VALUES (UUID(), 'Test', 'Seller', 'test_seller_commission@example.com', '+584149999999', 0, 'V-99999999', '1990-01-01', 1, NOW())
        ");
        return (int)$this->pdo->lastInsertId();
    }

    private function getOrCreateTestOrderId(): int
    {
        $stmt = $this->pdo->query("SELECT id FROM orders LIMIT 1");
        $id = $stmt->fetchColumn();
        if ($id) {
            return (int)$id;
        }

        $sellerId = $this->getOrCreateTestSellerId();
        $this->pdo->exec("
            INSERT INTO orders (uuid, order_number, user_id, orderable_type, orderable_id, amount_usd, exchange_rate, amount_ves, status, created_at)
            VALUES (UUID(), 'ORD-TEST-001', {$sellerId}, 'CONTRACT', 1, 100.00, 36.50, 3650.00, 'PAID', NOW())
        ");
        return (int)$this->pdo->lastInsertId();
    }

    private function getOrCreateTestBankId(): int
    {
        $stmt = $this->pdo->query("SELECT id FROM banks LIMIT 1");
        $id = $stmt->fetchColumn();
        if ($id) {
            return (int)$id;
        }

        $this->pdo->exec("
            INSERT INTO banks (code, name, is_active, created_at)
            VALUES ('0102', 'Banco de Venezuela', 1, NOW())
        ");
        return (int)$this->pdo->lastInsertId();
    }

    public function testSaveAndFindSellerProfile(): void
    {
        $sellerId = $this->getOrCreateTestSellerId();
        $bankId = $this->getOrCreateTestBankId();

        $profile = new SellerProfile(
            userId: $sellerId,
            commissionRate: 12.50,
            bankId: $bankId,
            accountNumber: '01021234567890123456',
            accountType: 'CORRIENTE',
            accountHolderName: 'Vendedor Prueba',
            accountHolderId: 'V-99999999',
            phonePagoMovil: '04149999999'
        );

        $saved = $this->sellerProfileRepo->save($profile);
        $this->assertSame($sellerId, $saved->getUserId());
        $this->assertEquals(12.50, $saved->getCommissionRate());

        $found = $this->sellerProfileRepo->findByUserId($sellerId);
        $this->assertNotNull($found);
        $this->assertSame($sellerId, $found->getUserId());
        $this->assertEquals(12.50, $found->getCommissionRate());
        $this->assertSame('01021234567890123456', $found->getAccountNumber());
        $this->assertSame('CORRIENTE', $found->getAccountType());
        $this->assertTrue($found->hasCompleteBankDetails());

        // Update rate
        $found->setCommissionRate(15.00);
        $updated = $this->sellerProfileRepo->save($found);
        $this->assertEquals(15.00, $updated->getCommissionRate());
    }

    public function testSaveAndFindCommission(): void
    {
        $sellerId = $this->getOrCreateTestSellerId();
        $orderId = $this->getOrCreateTestOrderId();

        $commission = new Commission(
            sellerId: $sellerId,
            orderId: $orderId,
            amountUsd: 15.00,
            percentage: 15.00,
            status: CommissionStatus::Pending
        );

        $saved = $this->commissionRepo->save($commission);
        $this->assertNotNull($saved->getId());
        $this->assertSame($sellerId, $saved->getSellerId());
        $this->assertSame($orderId, $saved->getOrderId());
        $this->assertEquals(15.00, $saved->getAmountUsd());
        $this->assertSame(CommissionStatus::Pending, $saved->getStatus());

        // Find by ID
        $found = $this->commissionRepo->findById($saved->getId());
        $this->assertNotNull($found);
        $this->assertSame($saved->getId(), $found->getId());
        $this->assertSame(CommissionStatus::Pending, $found->getStatus());

        // Find by Order ID
        $byOrder = $this->commissionRepo->findByOrderId($orderId);
        $this->assertNotNull($byOrder);
        $this->assertSame($saved->getId(), $byOrder->getId());

        // Find by Seller ID with status filter
        $bySeller = $this->commissionRepo->findBySellerId($sellerId, CommissionStatus::Pending);
        $this->assertNotEmpty($bySeller);

        // Update commission status to WITHDRAWAL_REQUESTED
        $found->requestWithdrawal();
        $updated = $this->commissionRepo->save($found);
        $this->assertSame(CommissionStatus::WithdrawalRequested, $updated->getStatus());

        // Mark as paid
        $updated->markAsPaid('REF-TEST-999');
        $paid = $this->commissionRepo->save($updated);
        $this->assertSame(CommissionStatus::Paid, $paid->getStatus());
        $this->assertSame('REF-TEST-999', $paid->getPaymentReference());
    }

    public function testWalletSummariesAndListing(): void
    {
        $sellerId = $this->getOrCreateTestSellerId();
        $orderId = $this->getOrCreateTestOrderId();

        // Insert pending commission
        $comm1 = new Commission(
            sellerId: $sellerId,
            orderId: $orderId,
            amountUsd: 25.00,
            percentage: 10.00,
            status: CommissionStatus::Pending
        );
        $this->commissionRepo->save($comm1);

        $summary = $this->commissionRepo->getSellerWalletSummary($sellerId);
        $this->assertArrayHasKey('pending_balance_usd', $summary);
        $this->assertArrayHasKey('paid_balance_usd', $summary);
        $this->assertArrayHasKey('withdrawal_requested_usd', $summary);
        $this->assertArrayHasKey('total_earned_usd', $summary);
        $this->assertGreaterThanOrEqual(25.00, $summary['pending_balance_usd']);

        $global = $this->commissionRepo->getGlobalWalletSummary();
        $this->assertArrayHasKey('total_commissions_usd', $global);
        $this->assertArrayHasKey('total_pending_usd', $global);
        $this->assertArrayHasKey('total_requested_usd', $global);
        $this->assertArrayHasKey('total_paid_usd', $global);
        $this->assertGreaterThanOrEqual(25.00, $global['total_commissions_usd']);

        $all = $this->commissionRepo->findAll(status: CommissionStatus::Pending, limit: 10, offset: 0);
        $this->assertNotEmpty($all);

        $count = $this->commissionRepo->countAll(status: CommissionStatus::Pending);
        $this->assertGreaterThanOrEqual(1, $count);
    }
}
