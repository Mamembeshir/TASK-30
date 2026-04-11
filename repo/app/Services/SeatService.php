<?php

namespace App\Services;

use App\Enums\HoldReleaseReason;
use App\Enums\SignupStatus;
use App\Enums\TripStatus;
use App\Events\SeatHeld;
use App\Events\SeatReleased;
use App\Events\SignupConfirmed;
use App\Models\SeatHold;
use App\Models\Trip;
use App\Models\TripSignup;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SeatService
{
    public function __construct(
        private readonly WaitlistService $waitlistService,
    ) {}

    /**
     * TRIP-05/06: Atomically decrement available_seats and create a 10-min hold.
     * Uses SELECT FOR UPDATE to prevent overselling.
     *
     * @throws RuntimeException (422) when no seats available or user already has active signup
     */
    public function holdSeat(Trip $trip, User $user, string $idempotencyKey): TripSignup
    {
        return DB::transaction(function () use ($trip, $user, $idempotencyKey) {
            // Lock the trip row for the duration of this transaction
            $trip = Trip::lockForUpdate()->findOrFail($trip->id);

            // TRIP-09: reject if user already has an active signup or waitlist entry
            $existing = TripSignup::where('trip_id', $trip->id)
                ->where('user_id', $user->id)
                ->whereIn('status', [SignupStatus::HOLD->value, SignupStatus::CONFIRMED->value])
                ->exists();

            if ($existing) {
                throw new RuntimeException('You already have an active signup for this trip.', 422);
            }

            // TRIP-06: no seats available
            if ($trip->available_seats <= 0 || $trip->status !== TripStatus::PUBLISHED) {
                throw new RuntimeException('No seats are currently available for this trip.', 422);
            }

            $holdMinutes = (int) config('medvoyage.seat_hold_minutes', 10);
            $expiresAt   = now()->addMinutes($holdMinutes);

            // Create the signup record
            $signup = TripSignup::create([
                'trip_id'          => $trip->id,
                'user_id'          => $user->id,
                'status'           => SignupStatus::HOLD->value,
                'hold_expires_at'  => $expiresAt,
                'idempotency_key'  => $idempotencyKey,
                'version'          => 1,
            ]);

            // Record seat hold metadata
            SeatHold::create([
                'trip_id'   => $trip->id,
                'signup_id' => $signup->id,
                'held_at'   => now(),
                'expires_at' => $expiresAt,
            ]);

            // Decrement available seats
            $trip->decrement('available_seats');
            $trip->refresh();

            // TRIP-06: if seats hit 0, transition trip to FULL
            if ($trip->available_seats === 0 && $trip->status === TripStatus::PUBLISHED) {
                $trip->status = TripStatus::FULL;
                $trip->saveWithLock();
            }

            AuditService::record('trip_signup.hold_created', 'TripSignup', $signup->id, null, [
                'trip_id'        => $trip->id,
                'user_id'        => $user->id,
                'hold_expires_at' => $expiresAt->toIso8601String(),
            ]);

            SeatHeld::dispatch($trip->fresh());

            return $signup;
        });
    }

    /**
     * Confirm a hold — links a payment and transitions status to CONFIRMED.
     */
    public function confirmSeat(TripSignup $signup, string $paymentId): TripSignup
    {
        if ($signup->status !== SignupStatus::HOLD) {
            throw new RuntimeException('Only HOLD signups can be confirmed.', 422);
        }

        if ($signup->isHoldExpired()) {
            throw new RuntimeException('Hold has expired. Please restart the booking.', 422);
        }

        return DB::transaction(function () use ($signup, $paymentId) {
            $before = ['status' => $signup->status->value];

            $signup->status       = SignupStatus::CONFIRMED;
            $signup->confirmed_at = now();
            $signup->payment_id   = $paymentId;
            $signup->saveWithLock();

            // Mark the hold as released (confirmed)
            $signup->hold?->update([
                'released'       => true,
                'released_at'    => now(),
                'release_reason' => HoldReleaseReason::CONFIRMED->value,
            ]);

            // Increment booking count on the trip
            $signup->trip()->increment('booking_count');
            $trip = $signup->trip()->first();

            AuditService::record('trip_signup.confirmed', 'TripSignup', $signup->id, $before, [
                'status'     => SignupStatus::CONFIRMED->value,
                'payment_id' => $paymentId,
            ]);

            SignupConfirmed::dispatch($trip, $signup->fresh());

            return $signup->fresh();
        });
    }

    /**
     * Release a seat — restores available_seats, marks hold as released,
     * and triggers waitlist offer if applicable.
     */
    public function releaseSeat(TripSignup $signup, HoldReleaseReason $reason): void
    {
        DB::transaction(function () use ($signup, $reason) {
            $trip = Trip::lockForUpdate()->findOrFail($signup->trip_id);

            $before = ['status' => $signup->status->value];

            $newStatus = match ($reason) {
                HoldReleaseReason::EXPIRED    => SignupStatus::EXPIRED,
                HoldReleaseReason::CANCELLED  => SignupStatus::CANCELLED,
                default                       => SignupStatus::EXPIRED,
            };

            $signup->status       = $newStatus;
            $signup->cancelled_at = now();
            $signup->saveWithLock();

            // Mark hold as released
            $signup->hold?->update([
                'released'       => true,
                'released_at'    => now(),
                'release_reason' => $reason->value,
            ]);

            // Restore seat count
            $trip->increment('available_seats');
            $trip->refresh();

            // If trip was FULL, transition back to PUBLISHED
            if ($trip->status === TripStatus::FULL && $trip->available_seats > 0) {
                $trip->status = TripStatus::PUBLISHED;
                $trip->saveWithLock();
            }

            AuditService::record('trip_signup.seat_released', 'TripSignup', $signup->id, $before, [
                'status' => $newStatus->value,
                'reason' => $reason->value,
            ]);

            SeatReleased::dispatch($trip->fresh());

            // Offer to next in waitlist
            $this->waitlistService->offerNextSeat($trip->fresh());
        });
    }

    /**
     * Cancel a confirmed signup — decrements booking_count and releases the seat.
     */
    public function cancelConfirmedSignup(TripSignup $signup): void
    {
        if ($signup->status !== SignupStatus::CONFIRMED) {
            throw new RuntimeException('Only CONFIRMED signups can be cancelled.', 422);
        }

        DB::transaction(function () use ($signup) {
            $trip = Trip::lockForUpdate()->findOrFail($signup->trip_id);

            $before = ['status' => $signup->status->value];

            $signup->status       = SignupStatus::CANCELLED;
            $signup->cancelled_at = now();
            $signup->saveWithLock();

            $trip->decrement('booking_count');
            $trip->increment('available_seats');
            $trip->refresh();

            if ($trip->status === TripStatus::FULL && $trip->available_seats > 0) {
                $trip->status = TripStatus::PUBLISHED;
                $trip->saveWithLock();
            }

            AuditService::record('trip_signup.cancelled', 'TripSignup', $signup->id, $before, [
                'status' => SignupStatus::CANCELLED->value,
            ]);

            SeatReleased::dispatch($trip->fresh());
            $this->waitlistService->offerNextSeat($trip->fresh());
        });
    }
}
