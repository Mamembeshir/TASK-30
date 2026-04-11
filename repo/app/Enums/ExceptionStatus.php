<?php

namespace App\Enums;

enum ExceptionStatus: string
{
    case OPEN        = 'OPEN';
    case RESOLVED    = 'RESOLVED';
    case WRITTEN_OFF = 'WRITTEN_OFF';

    public function label(): string
    {
        return match($this) {
            self::OPEN        => 'Open',
            self::RESOLVED    => 'Resolved',
            self::WRITTEN_OFF => 'Written Off',
        };
    }

    public function badgeVariant(): string
    {
        return match($this) {
            self::OPEN        => 'danger',
            self::RESOLVED    => 'success',
            self::WRITTEN_OFF => 'neutral',
        };
    }
}
