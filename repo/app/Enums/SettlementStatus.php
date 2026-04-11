<?php

namespace App\Enums;

enum SettlementStatus: string
{
    case OPEN       = 'OPEN';
    case CLOSED     = 'CLOSED';
    case RECONCILED = 'RECONCILED';
    case EXCEPTION  = 'EXCEPTION';

    public function label(): string
    {
        return match($this) {
            self::OPEN       => 'Open',
            self::CLOSED     => 'Closed',
            self::RECONCILED => 'Reconciled',
            self::EXCEPTION  => 'Exception',
        };
    }

    public function badgeVariant(): string
    {
        return match($this) {
            self::OPEN       => 'info',
            self::CLOSED     => 'neutral',
            self::RECONCILED => 'success',
            self::EXCEPTION  => 'danger',
        };
    }
}
