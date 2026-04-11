<?php

namespace App\Jobs;

use App\Enums\SignupStatus;
use App\Events\HoldExpiring;
use App\Models\TripSignup;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatches the `HoldExpiring` broadcast event at T-minus-2-minutes on the
 * user's private channel. The SignupWizard Livewire component already has a
 * listener wired up for this event — previously it was dead code because
 * nothing ever dispatched HoldExpiring. This job closes that gap as part of
 * the polling → WebSocket migration.
 *
 * Queued with `->delay($hold_expires_at->subMinutes(2))` by SeatService.
 */
class NotifyHoldExpiring implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $signupId,
    ) {}

    public function handle(): void
    {
        $signup = TripSignup::find($this->signupId);

        if ($signup === null || $signup->status !== SignupStatus::HOLD) {
            return;
        }

        // Hold was somehow extended past the original 2-min warning window
        // (or the queue ran late and we're already past expiry). In either
        // case there's no useful warning to send.
        if ($signup->isHoldExpired()) {
            return;
        }

        HoldExpiring::dispatch($signup);
    }
}
