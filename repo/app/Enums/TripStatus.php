<?php

namespace App\Enums;

enum TripStatus: string
{
    case DRAFT     = 'DRAFT';
    case PUBLISHED = 'PUBLISHED';
    case FULL      = 'FULL';
    case CLOSED    = 'CLOSED';
    case CANCELLED = 'CANCELLED';

    public function label(): string
    {
        return match($this) {
            self::DRAFT     => 'Draft',
            self::PUBLISHED => 'Published',
            self::FULL      => 'Full',
            self::CLOSED    => 'Closed',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function badgeVariant(): string
    {
        return match($this) {
            self::DRAFT     => 'neutral',
            self::PUBLISHED => 'success',
            self::FULL      => 'warning',
            self::CLOSED    => 'neutral',
            self::CANCELLED => 'danger',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::PUBLISHED;
    }

    public function canAcceptSignups(): bool
    {
        return $this === self::PUBLISHED;
    }

    /** @return array<TripStatus> */
    public function allowedTransitions(): array
    {
        return match($this) {
            self::DRAFT     => [self::PUBLISHED, self::CANCELLED],
            self::PUBLISHED => [self::FULL, self::CLOSED, self::CANCELLED],
            self::FULL      => [self::PUBLISHED, self::CLOSED, self::CANCELLED],
            self::CLOSED    => [self::CANCELLED],
            self::CANCELLED => [],
        };
    }
}
