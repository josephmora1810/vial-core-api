<?php

namespace App\Infrastructure\Database\Repositories\Payments;

use App\Domain\Payments\Entities\Payment;
use App\Domain\Payments\Enums\PaymentMethod;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Repositories\PaymentRepositoryInterface;
use App\Domain\Shared\ValueObjects\Uuid;
use DateTimeImmutable;
use PDO;

class MariaDbPaymentRepository implements PaymentRepositoryInterface
{
    public function __construct(private PDO $db) {}

    public function findById(int $id): ?Payment
    {
        $stmt = $this->db->prepare("SELECT * FROM payments WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->mapRow($row) : null;
    }

    public function findByUuid(string $uuid): ?Payment
    {
        $stmt = $this->db->prepare("SELECT * FROM payments WHERE uuid = :uuid LIMIT 1");
        $stmt->execute(['uuid' => trim($uuid)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->mapRow($row) : null;
    }

    public function existsCompletedByTransactionId(string $transactionId): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1 FROM payments
            WHERE transaction_id = :transaction_id
              AND status = 'completed'
            LIMIT 1
        ");
        $stmt->execute(['transaction_id' => trim($transactionId)]);

        return (bool)$stmt->fetchColumn();
    }

    public function findByOrderId(int $orderId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM payments
            WHERE order_id = :order_id
            ORDER BY id DESC
        ");
        $stmt->execute(['order_id' => $orderId]);

        $payments = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $payments[] = $this->mapRow($row);
        }
        return $payments;
    }

    public function save(Payment $payment): Payment
    {
        if ($payment->getId() === null) {
            $stmt = $this->db->prepare("
                INSERT INTO payments (
                    uuid, order_id, paid_by_user_id, amount, currency,
                    payment_method, commerce, transaction_id, status,
                    request_payload, response_payload, error_message, paid_at
                ) VALUES (
                    :uuid, :order_id, :paid_by_user_id, :amount, :currency,
                    :payment_method, :commerce, :transaction_id, :status,
                    :request_payload, :response_payload, :error_message, :paid_at
                )
            ");
            $stmt->execute([
                'uuid'             => $payment->getUuid()->getValue(),
                'order_id'         => $payment->getOrderId(),
                'paid_by_user_id'  => $payment->getPaidByUserId(),
                'amount'           => $payment->getAmount()->getAmount(),
                'currency'         => $payment->getAmount()->getCurrency(),
                'payment_method'   => $payment->getPaymentMethod()->getValue(),
                'commerce'         => $payment->getCommerce(),
                'transaction_id'   => $payment->getTransactionId(),
                'status'           => $payment->getStatus()->getValue(),
                'request_payload'  => $payment->getRequestPayload() !== null ? json_encode($payment->getRequestPayload()) : null,
                'response_payload' => $payment->getResponsePayload() !== null ? json_encode($payment->getResponsePayload()) : null,
                'error_message'    => $payment->getErrorMessage(),
                'paid_at'          => $payment->getPaidAt()?->format('Y-m-d H:i:s'),
            ]);
            $payment->setId((int)$this->db->lastInsertId());
            return $payment;
        }

        $stmt = $this->db->prepare("
            UPDATE payments
            SET status = :status,
                transaction_id = :transaction_id,
                response_payload = :response_payload,
                error_message = :error_message,
                paid_at = :paid_at
            WHERE id = :id
        ");
        $stmt->execute([
            'id'               => $payment->getId(),
            'status'           => $payment->getStatus()->getValue(),
            'transaction_id'   => $payment->getTransactionId(),
            'response_payload' => $payment->getResponsePayload() !== null ? json_encode($payment->getResponsePayload()) : null,
            'error_message'    => $payment->getErrorMessage(),
            'paid_at'          => $payment->getPaidAt()?->format('Y-m-d H:i:s'),
        ]);

        return $payment;
    }

    private function mapRow(array $row): Payment
    {
        return new Payment(
            orderId: (int)$row['order_id'],
            paidByUserId: (int)$row['paid_by_user_id'],
            amount: (float)$row['amount'],
            paymentMethod: PaymentMethod::fromString((string)$row['payment_method']),
            status: PaymentStatus::fromString((string)$row['status']),
            transactionId: $row['transaction_id'] !== null ? (string)$row['transaction_id'] : null,
            commerce: (string)($row['commerce'] ?? 'Bancaribe'),
            requestPayload: !empty($row['request_payload']) ? json_decode((string)$row['request_payload'], true) : null,
            responsePayload: !empty($row['response_payload']) ? json_decode((string)$row['response_payload'], true) : null,
            errorMessage: $row['error_message'] !== null ? (string)$row['error_message'] : null,
            paidAt: !empty($row['paid_at']) ? new DateTimeImmutable((string)$row['paid_at']) : null,
            uuid: new Uuid((string)$row['uuid']),
            id: (int)$row['id'],
            createdAt: !empty($row['created_at']) ? new DateTimeImmutable((string)$row['created_at']) : null
        );
    }
}
