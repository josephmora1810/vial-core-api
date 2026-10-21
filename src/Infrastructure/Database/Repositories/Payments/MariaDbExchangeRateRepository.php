<?php

namespace App\Infrastructure\Database\Repositories\Payments;

use App\Domain\Payments\Entities\ExchangeRate;
use App\Domain\Payments\Repositories\ExchangeRateRepositoryInterface;
use DateTimeImmutable;
use PDO;

class MariaDbExchangeRateRepository implements ExchangeRateRepositoryInterface
{
    public function __construct(private PDO $db) {}

    public function findByDate(string $currency, string $type, DateTimeImmutable $date): ?ExchangeRate
    {
        $stmt = $this->db->prepare("
            SELECT id, currency, type, rate, date
            FROM exchange_rates
            WHERE currency = :currency AND type = :type AND date = :date
            LIMIT 1
        ");
        $stmt->execute([
            'currency' => strtoupper(trim($currency)),
            'type'     => strtolower(trim($type)),
            'date'     => $date->format('Y-m-d'),
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->mapRow($row) : null;
    }

    public function findLatest(string $currency, string $type, ?DateTimeImmutable $sinceDate = null): ?ExchangeRate
    {
        $sql = "
            SELECT id, currency, type, rate, date
            FROM exchange_rates
            WHERE currency = :currency AND type = :type
        ";
        $params = [
            'currency' => strtoupper(trim($currency)),
            'type'     => strtolower(trim($type)),
        ];

        if ($sinceDate !== null) {
            $sql .= " AND date >= :since_date";
            $params['since_date'] = $sinceDate->format('Y-m-d');
        }

        $sql .= " ORDER BY date DESC, id DESC LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->mapRow($row) : null;
    }

    public function findLatestAll(): array
    {
        $stmt = $this->db->query("
            SELECT er.id, er.currency, er.type, er.rate, er.date
            FROM exchange_rates er
            INNER JOIN (
                SELECT currency, type, MAX(date) AS max_date
                FROM exchange_rates
                GROUP BY currency, type
            ) latest ON er.currency = latest.currency AND er.type = latest.type AND er.date = latest.max_date
            ORDER BY er.currency ASC, er.type ASC
        ");

        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = $this->mapRow($row);
        }
        return $results;
    }

    public function upsert(ExchangeRate $exchangeRate): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO exchange_rates (currency, type, rate, date)
            VALUES (:currency, :type, :rate, :date)
            ON DUPLICATE KEY UPDATE rate = VALUES(rate)
        ");

        $stmt->execute([
            'currency' => $exchangeRate->getCurrency()->getValue(),
            'type'     => $exchangeRate->getType(),
            'rate'     => $exchangeRate->getRate(),
            'date'     => $exchangeRate->getDate()->format('Y-m-d'),
        ]);
    }

    private function mapRow(array $row): ExchangeRate
    {
        return new ExchangeRate(
            currency: (string)$row['currency'],
            type: (string)$row['type'],
            rate: (float)$row['rate'],
            date: new DateTimeImmutable((string)$row['date']),
            id: (int)$row['id']
        );
    }
}
