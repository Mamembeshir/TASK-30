<?php

namespace App\Enums;

enum CaseStatus: string
{
    case SUBMITTED               = 'SUBMITTED';
    case INITIAL_REVIEW          = 'INITIAL_REVIEW';
    case MORE_MATERIALS_REQUESTED = 'MORE_MATERIALS_REQUESTED';
    case RE_REVIEW               = 'RE_REVIEW';
    case APPROVED                = 'APPROVED';
    case REJECTED                = 'REJECTED';

    public function label(): string
    {
        return match($this) {
            self::SUBMITTED                => 'Submitted',
            self::INITIAL_REVIEW           => 'Initial Review',
            self::MORE_MATERIALS_REQUESTED => 'More Materials Requested',
            self::RE_REVIEW                => 'Re-Review',
            self::APPROVED                 => 'Approved',
            self::REJECTED                 => 'Rejected',
        };
    }

    public function badgeVariant(): string
    {
        return match($this) {
            self::SUBMITTED                => 'info',
            self::INITIAL_REVIEW           => 'warning',
            self::MORE_MATERIALS_REQUESTED => 'warning',
            self::RE_REVIEW                => 'warning',
            self::APPROVED                 => 'success',
            self::REJECTED                 => 'danger',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::APPROVED, self::REJECTED]);
    }

    public function isActive(): bool
    {
        return ! $this->isTerminal();
    }

    /** @return array<CaseStatus> */
    public function allowedTransitions(): array
    {
        return match($this) {
            self::SUBMITTED                => [self::INITIAL_REVIEW],
            self::INITIAL_REVIEW           => [self::MORE_MATERIALS_REQUESTED, self::APPROVED, self::REJECTED],
            self::MORE_MATERIALS_REQUESTED => [self::RE_REVIEW],
            self::RE_REVIEW                => [self::APPROVED, self::REJECTED],
            self::APPROVED                 => [],
            self::REJECTED                 => [],
        };
    }
}
