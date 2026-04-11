<?php

namespace App\Enums;

enum RefundType: string
{
    case FULL    = 'FULL';
    case PARTIAL = 'PARTIAL';

    public function label(): string
    {
        return match($this) {
            self::FULL    => 'Full Refund',
            self::PARTIAL => 'Partial Refund',
        };
    }
}
