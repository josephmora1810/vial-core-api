<?php

namespace App\Application\Payments\DTOs;

readonly class BankBalanceResponseDto
{
    /**
     * @param list<array{account_number: string, currency_code: string, currency_name: string, available: float, balance: float, blocked: float}> $accounts
     */
    public function __construct(
        public bool $success,
        public float $balance,
        public array $accounts,
        public ?string $message = null,
        public mixed $raw_data = null
    ) {}

    /**
     * @param array<string, mixed> $data Resultado de BalanceGatewayInterface::getBalance()
     */
    public static function fromArray(array $data): self
    {
        $accounts = [];
        foreach (is_array($data['accounts'] ?? null) ? $data['accounts'] : [] as $account) {
            if (!is_array($account)) {
                continue;
            }
            $accounts[] = [
                'account_number' => (string)($account['account_number'] ?? ''),
                'currency_code'  => (string)($account['currency_code'] ?? 'VES'),
                'currency_name'  => (string)($account['currency_name'] ?? ''),
                'available'      => (float)($account['available'] ?? 0),
                'balance'        => (float)($account['balance'] ?? 0),
                'blocked'        => (float)($account['blocked'] ?? 0),
            ];
        }

        return new self(
            success: (bool)($data['success'] ?? false),
            balance: (float)($data['balance'] ?? 0),
            accounts: $accounts,
            message: isset($data['message']) ? (string)$data['message'] : null,
            raw_data: $data['raw_data'] ?? null
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success'  => $this->success,
            'balance'  => $this->balance,
            'accounts' => $this->accounts,
            'message'  => $this->message,
            'raw_data' => $this->raw_data,
        ];
    }
}