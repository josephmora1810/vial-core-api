<?php

namespace App\Domain\Towing\Enums;

use InvalidArgumentException;

enum TowServiceStatus: string 
{
    case Pending = 'PENDING';    
    case InRoute = 'IN_ROUTE';
    case InProgress = 'IN_PROGRESS';
    case Completed = 'COMPLETED';
    case CancelledByClient = 'CANCELLED_BY_CLIENT';
    case CancelledByDriver = 'CANCELLED_BY_DRIVER';
    case CancelledByTimeout = 'CANCELLED_BY_TIMEOUT';

    public static function fromString(string $value): self
    {
        $status = self::tryFrom(strtoupper(trim($value)));
        if ($status === null) {
            throw new InvalidArgumentException(sprintf(
                'El estado de servicio de remolque "%s" no es válido. Permitidos: %s.',
                $value,
                implode(', ', self::values())
            ));
        }
        return $status;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function isPending(): bool
    {
        return $this === self::Pending;
    }

    public function isInRoute(): bool
    {
        return $this === self::InRoute;
    }

    public function isInProgress(): bool
    {
        return $this === self::InProgress;
    }

    public function isCompleted(): bool
    {
        return $this === self::Completed;
    }


    public function isCancelledByClient(): bool
    {
        return $this === self::CancelledByClient;
    }

    public function isCancelledByDriver(): bool
    {
        return $this === self::CancelledByDriver;
    }

    public function isCancelledByTimeout(): bool
    {
        return $this === self::CancelledByTimeout;
    }

    public function isTerminal(): bool
    {
        return $this->isCompleted() || $this->isCancelledByClient() || $this->isCancelledByDriver() || $this->isCancelledByTimeout();
    }

    public function canBeAccepted(): bool
    {
        return $this->isPending();
    }

    public function canBeCancelled(): bool
    {
        return $this->isPending() || $this->isInRoute() || $this->isInProgress();
    }
}