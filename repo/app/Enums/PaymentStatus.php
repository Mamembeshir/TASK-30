<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case RECORDED           = 'RECORDED';
    case CONFIRMED          = 'CONFIRMED';
    case VOIDED             = 'VOIDED';
    case REFUNDED           = 'REFUNDED';
    case PARTIALLY_REFUNDED = 'PARTIALLY_REFUNDED';

    public function label(): string
    {
        return match($this) {
            self::RECORDED           => 'Recorded',
            self::CONFIRMED          => 'Confirmed',
            self::VOIDED             => 'Voided',
            self::REFUNDED           => 'Refunded',
            self::PARTIALLY_REFUNDED => 'Partially Refunded',
        };
    }

    public function badgeVariant(): string
    {
        return match($this) {
            self::RECORDED           => 'warning',
            self::CONFIRMED          => 'success',
            self::VOIDED             => 'danger',
            self::REFUNDED           => 'info',
            self::PARTIALLY_REFUNDED => 'warning',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::VOIDED, self::REFUNDED]);
    }

    /** @return array<PaymentStatus> */
    public function allowedTransitions(): array
    {
        return match($this) {
            self::RECORDED           => [self::CONFIRMED, self::VOIDED],
            self::CONFIRMED          => [self::REFUNDED, self::PARTIALLY_REFUNDED, self::VOIDED],
            self::PARTIALLY_REFUNDED => [self::REFUNDED],
            self::VOIDED             => [],
            self::REFUNDED           => [],
        };
    }
}
