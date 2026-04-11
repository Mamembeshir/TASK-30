<?php

namespace App\Jobs;

use App\Enums\WaitlistStatus;
use App\Models\TripWaitlistEntry;
use App\Services\WaitlistService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Real-time replacement for `medvoyage:expire-waitlist-offers`. Dispatched
 * from WaitlistService::offerNextSeat() with `->delay($offer_expires_at)`
 * so the 10-minute offer window is enforced precisely instead of polled.
 *
 * Idempotent: if the entry was already accepted/declined/expired through
 * another path, the service-layer guard in `expireOffer()` makes the call
 * a no-op.
 */
class ExpireWaitlistOfferJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $waitlistEntryId,
    ) {}

    public function handle(WaitlistService $waitlistService): void
    {
        $entry = TripWaitlistEntry::find($this->waitlistEntryId);

        if ($entry === null || $entry->status !== WaitlistStatus::OFFERED) {
            return;
        }

        // Same deterministic key as ExpireWaitlistOffers cron so a race
        // between the queue job and the polling safety-net collapses into
        // a single recorded expiry.
        $waitlistService->expireOffer($entry, 'waitlist.expire.' . $entry->id);
    }
}
