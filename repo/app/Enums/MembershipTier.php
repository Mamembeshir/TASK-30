<?php

namespace App\Enums;

enum MembershipTier: string
{
    case BASIC    = 'BASIC';
    case STANDARD = 'STANDARD';
    case PREMIUM  = 'PREMIUM';

    public function label(): string
    {
        return match($this) {
            self::BASIC    => 'Basic',
            self::STANDARD => 'Standard',
            self::PREMIUM  => 'Premium',
        };
    }

    public function rank(): int
    {
        return match($this) {
            self::BASIC    => 1,
            self::STANDARD => 2,
            self::PREMIUM  => 3,
        };
    }

    public function isHigherThan(self $other): bool
    {
        return $this->rank() > $other->rank();
    }
}
