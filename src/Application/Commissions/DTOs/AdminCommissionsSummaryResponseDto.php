<?php

namespace App\Application\Commissions\DTOs;

readonly class AdminCommissionsSummaryResponseDto
{
    /**
     * @param array<string, float> $summary
     * @param array{total: int, limit: int, offset: int} $pagination
     * @param list<array<string, mixed>> $commissions
     */
    public function __construct(
        public array $summary,
        public array $pagination,
        public array $commissions
    ) {}

    public static function create(
        array $summary,
        array $pagination,
        array $commissions
    ): self {
        return new self(
            summary: $summary,
            pagination: $pagination,
            commissions: $commissions
        );
    }

    public function toArray(): array
    {
        return [
            'summary'     => $this->summary,
            'pagination'  => $this->pagination,
            'commissions' => $this->commissions,
        ];
    }
}
