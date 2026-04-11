<?php

namespace App\Jobs;

use App\Enums\HoldReleaseReason;
use App\Enums\SignupStatus;
use App\Models\TripSignup;
use App\Services\SeatService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Real-time replacement for the polling-based `medvoyage:expire-seat-holds`
 * command. Dispatched with `->delay($hold_expires_at)` by SeatService::holdSeat()
 * so that the hold releases precisely when it expires (no 60s slip from cron).
 *
 * The job is idempotent: if the signup was already confirmed, cancelled, or
 * expired through some other path (user completed payment, user cancelled,
 * safety-net cron job already ran), the handle() method no-ops.
 */
class ReleaseExpiredHold implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $signupId,
    ) {}

    public function handle(SeatService $seatService): void
    {
        $signup = TripSignup::find($this->signupId);

        if ($signup === null) {
            return;
        }

        // No longer in HOLD → user confirmed payment or the safety-net
        // scheduled job already handled it. Either way, nothing to do.
        if ($signup->status !== SignupStatus::HOLD) {
            return;
        }

        // Not yet expired — this can only happen if the hold was extended,
        // which we don't currently support. Still, guard defensively.
        if (! $signup->isHoldExpired()) {
            return;
        }

        $seatService->releaseSeat($signup, HoldReleaseReason::EXPIRED);
    }
}
