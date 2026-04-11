<?php

namespace App\Services;

use App\Enums\SignupStatus;
use App\Enums\WaitlistStatus;
use App\Events\WaitlistOfferMade;
use App\Models\Trip;
use App\Models\TripWaitlistEntry;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WaitlistService
{
    /**
     * Join the waitlist for a trip. Assigns next position.
     * TRIP-09: one active waitlist entry per user per trip.
     */
    public function joinWaitlist(Trip $trip, User $user): TripWaitlistEntry
    {
        $existing = TripWaitlistEntry::where('trip_id', $trip->id)
            ->where('user_id', $user->id)
            ->whereIn('status', [WaitlistStatus::WAITING->value, WaitlistStatus::OFFERED->value])
            ->exists();

        if ($existing) {
            throw new RuntimeException('You are already on the waitlist for this trip.', 422);
        }

        $position = TripWaitlistEntry::where('trip_id', $trip->id)
            ->whereIn('status', [WaitlistStatus::WAITING->value, WaitlistStatus::OFFERED->value])
            ->max('position') + 1;

        $entry = TripWaitlistEntry::create([
            'trip_id'  => $trip->id,
            'user_id'  => $user->id,
            'position' => $position,
            'status'   => WaitlistStatus::WAITING->value,
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
     */
    public function declineOffer(TripWaitlistEntry $entry): void
    {
        if ($entry->status !== WaitlistStatus::OFFERED) {
            throw new RuntimeException('No active offer to decline.', 422);
        }

        DB::transaction(function () use ($entry) {
            $before = ['status' => $entry->status->value];

            $entry->status = WaitlistStatus::DECLINED;
            $entry->save();

            AuditService::record('waitlist.offer_declined', 'TripWaitlistEntry', $entry->id, $before, [
                'status' => WaitlistStatus::DECLINED->value,
            ]);

            $this->offerNextSeat($entry->trip);
        });
    }

    /**
     * Expire an offer that was not accepted in time.
     */
    public function expireOffer(TripWaitlistEntry $entry): void
    {
        if ($entry->status !== WaitlistStatus::OFFERED) {
            return;
        }

        DB::transaction(function () use ($entry) {
            $before = ['status' => $entry->status->value];

            $entry->status = WaitlistStatus::EXPIRED;
            $entry->save();

            AuditService::record('waitlist.offer_expired', 'TripWaitlistEntry', $entry->id, $before, [
                'status' => WaitlistStatus::EXPIRED->value,
            ]);

            $this->offerNextSeat($entry->trip);
        });
    }
}
