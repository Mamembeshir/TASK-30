<?php

namespace App\Enums;

enum ExceptionType: string
{
    case VARIANCE            = 'VARIANCE';
    case MISSING_CONFIRMATION = 'MISSING_CONFIRMATION';
    case DUPLICATE_PAYMENT   = 'DUPLICATE_PAYMENT';
    case ORPHAN_REFUND       = 'ORPHAN_REFUND';

    public function label(): string
    {
        return match($this) {
            self::VARIANCE             => 'Amount Variance',
            self::MISSING_CONFIRMATION => 'Missing Confirmation',
            self::DUPLICATE_PAYMENT    => 'Duplicate Payment',
            self::ORPHAN_REFUND        => 'Orphan Refund',
        };
    }
}
