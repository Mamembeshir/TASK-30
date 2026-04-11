<?php

namespace App\Enums;

enum UserStatus: string
{
    case PENDING     = 'PENDING';
    case ACTIVE      = 'ACTIVE';
    case SUSPENDED   = 'SUSPENDED';
    case DEACTIVATED = 'DEACTIVATED';

    public function label(): string
    {
        return match($this) {
            self::PENDING     => 'Pending',
            self::ACTIVE      => 'Active',
            self::SUSPENDED   => 'Suspended',
            self::DEACTIVATED => 'Deactivated',
        };
    }

    public function badgeVariant(): string
    {
        return match($this) {
            self::PENDING     => 'warning',
            self::ACTIVE      => 'success',
            self::SUSPENDED   => 'danger',
            self::DEACTIVATED => 'neutral',
        };
    }

    public function canLogin(): bool
    {
        return $this === self::ACTIVE;
    }

    /** @return array<UserStatus> */
    public function allowedTransitions(): array
    {
        return match($this) {
            self::PENDING     => [self::ACTIVE],
            self::ACTIVE      => [self::SUSPENDED, self::DEACTIVATED],
            self::SUSPENDED   => [self::ACTIVE, self::DEACTIVATED],
            self::DEACTIVATED => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
