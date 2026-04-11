<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case DRAFT  = 'DRAFT';
    case ISSUED = 'ISSUED';
    case PAID   = 'PAID';
    case VOIDED = 'VOIDED';

    public function label(): string
    {
        return match($this) {
            self::DRAFT  => 'Draft',
            self::ISSUED => 'Issued',
            self::PAID   => 'Paid',
            self::VOIDED => 'Voided',
        };
    }

    public function badgeVariant(): string
    {
        return match($this) {
            self::DRAFT  => 'neutral',
            self::ISSUED => 'warning',
            self::PAID   => 'success',
            self::VOIDED => 'danger',
        };
    }
}
