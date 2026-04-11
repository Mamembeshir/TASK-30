<?php

namespace App\Enums;

enum HoldReleaseReason: string
{
    case CONFIRMED = 'CONFIRMED';
    case EXPIRED   = 'EXPIRED';
    case CANCELLED = 'CANCELLED';
    case MANUAL    = 'MANUAL';

    public function label(): string
    {
        return match($this) {
            self::CONFIRMED => 'Payment Confirmed',
            self::EXPIRED   => 'Hold Expired',
            self::CANCELLED => 'Signup Cancelled',
            self::MANUAL    => 'Manually Released',
        };
    }
}
