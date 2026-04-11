<?php

namespace App\Enums;

enum OrderType: string
{
    case PURCHASE = 'PURCHASE';
    case RENEWAL  = 'RENEWAL';
    case TOP_UP   = 'TOP_UP';

    public function label(): string
    {
        return match($this) {
            self::PURCHASE => 'New Purchase',
            self::RENEWAL  => 'Renewal',
            self::TOP_UP   => 'Plan Upgrade (Top-up)',
        };
    }
}
