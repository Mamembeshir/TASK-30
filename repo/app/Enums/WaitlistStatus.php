<?php

namespace App\Enums;

enum WaitlistStatus: string
{
    case WAITING  = 'WAITING';
    case OFFERED  = 'OFFERED';
    case ACCEPTED = 'ACCEPTED';
    case DECLINED = 'DECLINED';
    case EXPIRED  = 'EXPIRED';

    public function label(): string
    {
        return match($this) {
            self::WAITING  => 'Waiting',
            self::OFFERED  => 'Seat Offered',
            self::ACCEPTED => 'Accepted',
            self::DECLINED => 'Declined',
            self::EXPIRED  => 'Expired',
        };
    }

    public function badgeVariant(): string
    {
        return match($this) {
            self::WAITING  => 'info',
            self::OFFERED  => 'warning',
            self::ACCEPTED => 'success',
            self::DECLINED => 'neutral',
            self::EXPIRED  => 'neutral',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::WAITING, self::OFFERED]);
    }
}
