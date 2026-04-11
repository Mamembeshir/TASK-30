<?php

namespace App\Enums;

enum TripDifficulty: string
{
    case EASY        = 'EASY';
    case MODERATE    = 'MODERATE';
    case CHALLENGING = 'CHALLENGING';

    public function label(): string
    {
        return match($this) {
            self::EASY        => 'Easy',
            self::MODERATE    => 'Moderate',
            self::CHALLENGING => 'Challenging',
        };
    }

    public function badgeVariant(): string
    {
        return match($this) {
            self::EASY        => 'success',
            self::MODERATE    => 'warning',
            self::CHALLENGING => 'danger',
        };
    }
}
