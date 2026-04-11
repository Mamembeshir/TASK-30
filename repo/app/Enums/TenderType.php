<?php

namespace App\Enums;

enum TenderType: string
{
    case CASH         = 'CASH';
    case CHECK        = 'CHECK';
    case CARD_ON_FILE = 'CARD_ON_FILE';

    public function label(): string
    {
        return match($this) {
            self::CASH         => 'Cash',
            self::CHECK        => 'Check',
            self::CARD_ON_FILE => 'Card on File',
        };
    }

    public function requiresReference(): bool
    {
        return in_array($this, [self::CHECK, self::CARD_ON_FILE]);
    }
}
