<?php

namespace App\Services;

use App\Enums\SignupStatus;
use App\Enums\TripStatus;
use App\Enums\WaitlistStatus;
use App\Events\WaitlistOfferMade;
use App\Jobs\ExpireWaitlistOfferJob;
use App\Models\Trip;
use App\Models\TripWaitlistEntry;
use App\Models\User;
use App\Services\AuditService;
use App\Services\IdempotencyStore;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WaitlistService
{
    /**
     * Join the waitlist for a trip. Assigns next position.
     *
     * Participates in the universal service-layer idempotency contract:
     * callers MUST pass a stable `$idempotencyKey` derived from (trip, user).
     * Retries with the same key return the existing entry instead of
     * creating a second row or throwing.
     *
     * TRIP-09: one active waitlist entry per user per trip — enforced by
     * both the idempotency key lookup AND the (trip_id, user_id) unique
     * constraint on `trip_waitlist_entries` as a second line of defense.
     *
     * Capacity gate (TRIP-09 / FIN audit Issue 3): the waitlist only exists
     * to absorb demand once the trip has no available seats. Joining while
     * seats are still on offer lets a user bypass the normal hold flow and
     * silently land in a queue they should have walked past — so we refuse
     * the call unless the trip is either already in FULL status or has a
     * current `available_seats` count of zero (the two states are kept in
     * sync by SeatService but we check both to avoid trusting a single
     * column for correctness). Retries of an *already-joined* user are
     * exempt so that reopening the page after seats free up does not blow
     * up an in-flight waitlist entry.
     */
    public function joinWaitlist(Trip $trip, User $user, string $idempotencyKey): TripWaitlistEntry
    {
        // 1. Primary idempotency check: a retry with the same caller-stable key
        //    short-circuits to the existing row without touching any other
        //    indexes. Same contract as SeatService::holdSeat,
        //    MembershipService::purchase, PaymentService::recordPayment, etc.
        $existingByKey = TripWaitlistEntry::where('idempotency_key', $idempotencyKey)->first();
        if ($existingByKey) {
            return $existingByKey;
        }

        // 2. Natural-key safety net: the same user may have an active entry
        //    created *before* the idempotency-key contract existed, or under
        //    a different deterministic key (e.g. tests). Return that rather
        //    than tripping the unique (trip_id, user_id) constraint on insert.
        $existingByNaturalKey = TripWaitlistEntry::where('trip_id', $trip->id)
            ->where('user_id', $user->id)
            ->whereIn('status', [WaitlistStatus::WAITING->value, WaitlistStatus::OFFERED->value])
            ->first();

        if ($existingByNaturalKey) {
            return $existingByNaturalKey;
        }

        // 3. Capacity gate — evaluated *after* the idempotency lookups so an
        //    existing entry is still reachable if the trip reopened. We
        //    re-read the trip row to make the check resistant to stale
        //    in-memory copies (e.g. a Livewire property held over a
        //    websocket event).
        $current = $trip->fresh();
        $hasSeats    = $current->available_seats > 0;
        $statusOpen  = $current->status === TripStatus::PUBLISHED;

        if ($hasSeats && $statusOpen) {
            throw new RuntimeException(
                'Cannot join waitlist while seats are still available. Hold a seat instead.',
                422
            );
        }

        // Administratively closed/cancelled trips should not accept new
        // waitlist entries either — only FULL (or a stale PUBLISHED row
        // whose seats have actually run out) is a valid source state.
        if (! in_array($current->status, [TripStatus::PUBLISHED, TripStatus::FULL], true)) {
            throw new RuntimeException(
                "Cannot join waitlist for a {$current->status->value} trip.",
                422
            );
        }

        $position = TripWaitlistEntry::where('trip_id', $trip->id)
            ->whereIn('status', [WaitlistStatus::WAITING->value, WaitlistStatus::OFFERED->value])
            ->max('position') + 1;

        $entry = TripWaitlistEntry::create([
            'trip_id'         => $trip->id,
            'user_id'         => $user->id,
            'position'        => $position,
            'status'          => WaitlistStatus::WAITING->value,
            'idempotency_key' => $idempotencyKey,
        ]);

        AuditService::record('waitlist.joined', 'TripWaitlistEntry', $entry->id, null, [
            'trip_id'  => $trip->id,
            'user_id'  => $user->id,
            'position' => $position,
        ]);

        return $entry;
    }

    /**
     * TRIP-07: Offer a seat to the next WAITING entry (FIFO by position).
     * Called after a seat is released. No-op if no waitlist or trip is not PUBLISHED/FULL.
     */
    public function offerNextSeat(Trip $trip): void
    {
        $entry = TripWaitlistEntry::where('trip_id', $trip->id)
            ->where('status', WaitlistStatus::WAITING->value)
            ->orderBy('position')
            ->first();

        if (! $entry) {
            return;
        }

        $offerMinutes  = (int) config('medvoyage.waitlist_offer_minutes', 10);
        $offerExpiresAt = now()->addMinutes($offerMinutes);

        $before = ['status' => $entry->status->value];

        $entry->status          = WaitlistStatus::OFFERED;
        $entry->offered_at      = now();
        $entry->offer_expires_at = $offerExpiresAt;
        $entry->save();

        AuditService::record('waitlist.offer_made', 'TripWaitlistEntry', $entry->id, $before, [
            'status'          => WaitlistStatus::OFFERED->value,
            'offer_expires_at' => $offerExpiresAt->toIso8601String(),
        ]);

        WaitlistOfferMade::dispatch($trip, $entry);

        // Real-time expiry: schedule the 10-minute offer window as a delayed
        // queue job instead of relying on the every-minute polling command.
        // See docs/questions.md §1.4. Dispatched directly (not via
        // afterCommit) so RefreshDatabase-wrapped tests still see the
        // dispatch; the job re-fetches the entry on execution and no-ops if
        // the enclosing transaction rolled back in production.
        ExpireWaitlistOfferJob::dispatch($entry->id)->delay($offerExpiresAt);
    }

    /**
     * Accept a waitlist offer — creates a seat hold for the user.
     * SeatService resolved from container to avoid circular constructor injection.
     */
    public function acceptOffer(TripWaitlistEntry $entry, string $idempotencyKey): \App\Models\TripSignup
    {
        if ($entry->status !== WaitlistStatus::OFFERED) {
            throw new RuntimeException('No active offer to accept.', 422);
        }

        if ($entry->isOfferExpired()) {
            throw new RuntimeException('Offer has expired.', 422);
        }

        return DB::transaction(function () use ($entry, $idempotencyKey) {
            $before = ['status' => $entry->status->value];

            $entry->status = WaitlistStatus::ACCEPTED;
            $entry->save();

            AuditService::record('waitlist.offer_accepted', 'TripWaitlistEntry', $entry->id, $before, [
                'status' => WaitlistStatus::ACCEPTED->value,
            ]);

            $user        = $entry->user;
            $trip        = $entry->trip;
            $seatService = app(SeatService::class);

            return $seatService->holdSeat($trip, $user, $idempotencyKey);
        });
    }

    /**
     * Decline a waitlist offer — moves entry to DECLINED and offers to next in line.
     *
     * `$idempotencyKey` is REQUIRED (FIN audit Issue 4).
     */
    public function declineOffer(TripWaitlistEntry $entry, string $idempotencyKey): void
    {
        $store = new IdempotencyStore();
        if ($store->alreadyProcessed($idempotencyKey, 'waitlist.decline_offer', $entry->id)) {
            return;
        }

        if ($entry->status !== WaitlistStatus::OFFERED) {
            throw new RuntimeException('No active offer to decline.', 422);
        }

        DB::transaction(function () use ($entry, $idempotencyKey, $store) {
            $before = ['status' => $entry->status->value];

            $entry->status = WaitlistStatus::DECLINED;
            $entry->save();

            AuditService::record('waitlist.offer_declined', 'TripWaitlistEntry', $entry->id, $before, [
                'status' => WaitlistStatus::DECLINED->value,
            ]);

            $store->record($idempotencyKey, 'waitlist.decline_offer', 'TripWaitlistEntry', $entry->id);

            $this->offerNextSeat($entry->trip);
        });
    }

    /**
     * Expire an offer that was not accepted in time.
     *
     * `$idempotencyKey` is REQUIRED (FIN audit Issue 4) — prevents
     * double-expiry when both the queue job and the cron safety-net fire
     * for the same entry. Both callers pass `waitlist.expire.{entryId}`.
     */
    public function expireOffer(TripWaitlistEntry $entry, string $idempotencyKey): void
    {
        $store = new IdempotencyStore();
        if ($store->alreadyProcessed($idempotencyKey, 'waitlist.expire_offer', $entry->id)) {
            return;
        }

        if ($entry->status !== WaitlistStatus::OFFERED) {
            return;
        }

        DB::transaction(function () use ($entry, $idempotencyKey, $store) {
            $before = ['status' => $entry->status->value];

            $entry->status = WaitlistStatus::EXPIRED;
            $entry->save();

            AuditService::record('waitlist.offer_expired', 'TripWaitlistEntry', $entry->id, $before, [
                'status' => WaitlistStatus::EXPIRED->value,
            ]);

            $store->record($idempotencyKey, 'waitlist.expire_offer', 'TripWaitlistEntry', $entry->id);

            $this->offerNextSeat($entry->trip);
        });
    }
}
