<?php

namespace App\Enums;

enum UserRole: string
{
    case MEMBER                 = 'MEMBER';
    case DOCTOR                 = 'DOCTOR';
    case CREDENTIALING_REVIEWER = 'CREDENTIALING_REVIEWER';
    case FINANCE_SPECIALIST     = 'FINANCE_SPECIALIST';
    case ADMIN                  = 'ADMIN';

    public function label(): string
    {
        return match($this) {
            self::MEMBER                 => 'Member',
            self::DOCTOR                 => 'Doctor',
            self::CREDENTIALING_REVIEWER => 'Credentialing Reviewer',
            self::FINANCE_SPECIALIST     => 'Finance Specialist',
            self::ADMIN                  => 'Administrator',
        };
    }
}
