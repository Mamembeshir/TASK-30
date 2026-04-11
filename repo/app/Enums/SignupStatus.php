<?php

namespace App\Enums;

enum SignupStatus: string
{
    case HOLD      = 'HOLD';
    case CONFIRMED = 'CONFIRMED';
    case CANCELLED = 'CANCELLED';
    case EXPIRED   = 'EXPIRED';

    public function label(): string
    {
        return match($this) {
            self::HOLD      => 'On Hold',
            self::CONFIRMED => 'Confirmed',
            self::CANCELLED => 'Cancelled',
            self::EXPIRED   => 'Expired',
        };
    }

    public function badgeVariant(): string
    {
        return match($this) {
            self::HOLD      => 'warning',
            self::CONFIRMED => 'success',
            self::CANCELLED => 'danger',
            self::EXPIRED   => 'neutral',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::HOLD, self::CONFIRMED]);
    }

    public function occupiesSeat(): bool
    {
        return in_array($this, [self::HOLD, self::CONFIRMED]);
    }
}
