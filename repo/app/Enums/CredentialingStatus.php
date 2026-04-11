<?php

namespace App\Enums;

enum CredentialingStatus: string
{
    case NOT_SUBMITTED           = 'NOT_SUBMITTED';
    case UNDER_REVIEW            = 'UNDER_REVIEW';
    case MORE_MATERIALS_REQUESTED = 'MORE_MATERIALS_REQUESTED';
    case APPROVED                = 'APPROVED';
    case REJECTED                = 'REJECTED';
    case EXPIRED                 = 'EXPIRED';

    public function label(): string
    {
        return match($this) {
            self::NOT_SUBMITTED            => 'Not Submitted',
            self::UNDER_REVIEW             => 'Under Review',
            self::MORE_MATERIALS_REQUESTED => 'More Materials Requested',
            self::APPROVED                 => 'Approved',
            self::REJECTED                 => 'Rejected',
            self::EXPIRED                  => 'Expired',
        };
    }

    public function badgeVariant(): string
    {
        return match($this) {
            self::NOT_SUBMITTED            => 'neutral',
            self::UNDER_REVIEW             => 'warning',
            self::MORE_MATERIALS_REQUESTED => 'warning',
            self::APPROVED                 => 'success',
            self::REJECTED                 => 'danger',
            self::EXPIRED                  => 'danger',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::APPROVED, self::REJECTED]);
    }

    public function canSubmitNewCase(): bool
    {
        // PRD 10.3: REJECTED and EXPIRED doctors may open a new case; APPROVED may also recredential
        return in_array($this, [self::NOT_SUBMITTED, self::APPROVED, self::REJECTED, self::EXPIRED]);
    }
}
