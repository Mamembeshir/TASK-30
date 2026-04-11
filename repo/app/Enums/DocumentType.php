<?php

namespace App\Enums;

enum DocumentType: string
{
    case LICENSE            = 'LICENSE';
    case BOARD_CERTIFICATION = 'BOARD_CERTIFICATION';
    case CV                 = 'CV';
    case INSURANCE          = 'INSURANCE';
    case OTHER              = 'OTHER';

    public function label(): string
    {
        return match($this) {
            self::LICENSE             => 'Medical License',
            self::BOARD_CERTIFICATION => 'Board Certification',
            self::CV                  => 'Curriculum Vitae (CV)',
            self::INSURANCE           => 'Malpractice Insurance',
            self::OTHER               => 'Other Document',
        };
    }
}
