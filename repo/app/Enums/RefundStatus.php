<?php

namespace App\Enums;

enum RefundStatus: string
{
    case PENDING   = 'PENDING';
    case APPROVED  = 'APPROVED';
    case PROCESSED = 'PROCESSED';
    case REJECTED  = 'REJECTED';

    public function label(): string
    {
        return match($this) {
            self::PENDING   => 'Pending',
            self::APPROVED  => 'Approved',
            self::PROCESSED => 'Processed',
            self::REJECTED  => 'Rejected',
        };
    }

    public function badgeVariant(): string
    {
        return match($this) {
            self::PENDING   => 'warning',
            self::APPROVED  => 'info',
            self::PROCESSED => 'success',
            self::REJECTED  => 'danger',
        };
    }
}
