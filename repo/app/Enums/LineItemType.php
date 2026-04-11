<?php

namespace App\Enums;

enum LineItemType: string
{
    case TRIP_SIGNUP   = 'TRIP_SIGNUP';
    case MEMBERSHIP    = 'MEMBERSHIP';
    case ADJUSTMENT    = 'ADJUSTMENT';
    case REFUND_CREDIT = 'REFUND_CREDIT';

    public function label(): string
    {
        return match($this) {
            self::TRIP_SIGNUP   => 'Trip Signup',
            self::MEMBERSHIP    => 'Membership',
            self::ADJUSTMENT    => 'Adjustment',
            self::REFUND_CREDIT => 'Refund Credit',
        };
    }
}
