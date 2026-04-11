<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING            = 'PENDING';
    case PAID               = 'PAID';
    case REFUNDED           = 'REFUNDED';
    case PARTIALLY_REFUNDED = 'PARTIALLY_REFUNDED';
    case VOIDED             = 'VOIDED';

    public function label(): string
    {
        return match($this) {
            self::PENDING            => 'Pending',
            self::PAID               => 'Paid',
            self::REFUNDED           => 'Refunded',
            self::PARTIALLY_REFUNDED => 'Partially Refunded',
            self::VOIDED             => 'Voided',
        };
    }

    public function badgeVariant(): string
    {
        return match($this) {
            self::PENDING            => 'warning',
            self::PAID               => 'success',
            self::REFUNDED           => 'info',
            self::PARTIALLY_REFUNDED => 'warning',
            self::VOIDED             => 'danger',
        };
    }

    public function isActive(): bool
    {
        return $this === self::PAID;
    }
}
