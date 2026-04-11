<?php

namespace App\Enums;

enum ReviewStatus: string
{
    case ACTIVE  = 'ACTIVE';
    case FLAGGED = 'FLAGGED';
    case REMOVED = 'REMOVED';

    public function label(): string
    {
        return match($this) {
            self::ACTIVE  => 'Active',
            self::FLAGGED => 'Flagged',
            self::REMOVED => 'Removed',
        };
    }

    public function badgeVariant(): string
    {
        return match($this) {
            self::ACTIVE  => 'success',
            self::FLAGGED => 'warning',
            self::REMOVED => 'danger',
        };
    }
}
