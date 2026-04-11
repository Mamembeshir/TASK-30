<?php

namespace App\Enums;

enum CaseAction: string
{
    case SUBMIT            = 'SUBMIT';
    case ASSIGN            = 'ASSIGN';
    case START_REVIEW      = 'START_REVIEW';
    case REQUEST_MATERIALS = 'REQUEST_MATERIALS';
    case RECEIVE_MATERIALS = 'RECEIVE_MATERIALS';
    case APPROVE           = 'APPROVE';
    case REJECT            = 'REJECT';

    public function label(): string
    {
        return match($this) {
            self::SUBMIT            => 'Submitted',
            self::ASSIGN            => 'Assigned Reviewer',
            self::START_REVIEW      => 'Started Review',
            self::REQUEST_MATERIALS => 'Requested More Materials',
            self::RECEIVE_MATERIALS => 'Received Materials',
            self::APPROVE           => 'Approved',
            self::REJECT            => 'Rejected',
        };
    }
}
