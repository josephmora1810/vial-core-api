<?php

declare(strict_types=1);

namespace App\Application\Towing\DTOs;

readonly class SosRequestResponseDto
{
    /**
     * @param TowServiceResponseDto|array<string, mixed> $service
     * @param array<string, mixed>|null $order
     * @param array<string, mixed> $route
     */
    public function __construct(
        public TowServiceResponseDto|array $service,
        public bool $requires_payment,
        public ?array $order,
        public array $route
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            service: $data['service'] instanceof TowServiceResponseDto ? $data['service'] : (array)($data['service'] ?? []),
            requires_payment: (bool)($data['requires_payment'] ?? false),
            order: isset($data['order']) ? (array)$data['order'] : null,
            route: (array)($data['route'] ?? [])
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'service'          => $this->service instanceof TowServiceResponseDto ? $this->service->toArray() : $this->service,
            'requires_payment' => $this->requires_payment,
            'order'            => $this->order,
            'route'            => $this->route,
        ];
    }
}
