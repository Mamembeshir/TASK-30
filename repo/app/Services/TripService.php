<?php

namespace App\Services;

use App\Enums\HoldReleaseReason;
use App\Enums\SignupStatus;
use App\Enums\TripStatus;
use App\Enums\WaitlistStatus;
use App\Events\TripStatusChanged;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Trip;
use App\Models\User;
use App\Services\AuditService;
use App\Services\IdempotencyStore;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TripService
{
    public function __construct(
        private readonly SeatService     $seatService,
        private readonly WaitlistService $waitlistService,
    ) {}

    /**
     * Create a new trip in DRAFT status.
     *
     * Participates in the universal service-layer idempotency contract
     * (`docs/design.md:70-73`, audit Issue 3). Callers MUST pass a stable
     * `$idempotencyKey`. A retry with the same key short-circuits to the
     * existing row without creating a second draft. Combined with the
     * unique constraint on `trips.idempotency_key`, this collapses racing
     * double-submits onto one row instead of two.
     */
    public function create(array $data, User $creator, string $idempotencyKey): Trip
    {
        $existing = Trip::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return $existing;
        }

        $trip = Trip::create(array_merge($data, [
            'status'          => TripStatus::DRAFT->value,
            'available_seats' => $data['total_seats'],
            'booking_count'   => 0,
            'created_by'      => $creator->id,
            'idempotency_key' => $idempotencyKey,
        ]));

        AuditService::record('trip.created', 'Trip', $trip->id, null, [
            'title'  => $trip->title,
            'status' => $trip->status->value,
        ]);

        return $trip;
    }

    /**
     * Update trip metadata (only allowed in DRAFT).
     */
    public function update(Trip $trip, array $data, ?string $idempotencyKey = null): Trip
    {
        $store = new IdempotencyStore();
        if ($idempotencyKey && $store->alreadyProcessed($idempotencyKey, 'trip.update', $trip->id)) {
            return $trip->fresh();
        }

        if ($trip->status !== TripStatus::DRAFT) {
            throw new RuntimeException('Only DRAFT trips can be edited.', 422);
        }

        $before = $trip->only(array_keys($data));
        $trip->fill($data);

        // If total_seats changed, adjust available_seats proportionally
        if (isset($data['total_seats'])) {
            $delta = $data['total_seats'] - $trip->getOriginal('total_seats');
            $trip->available_seats = max(0, $trip->available_seats + $delta);
        }

        $trip->saveWithLock();

        AuditService::record('trip.updated', 'Trip', $trip->id, $before, $trip->only(array_keys($data)));

        if ($idempotencyKey) {
            $store->record($idempotencyKey, 'trip.update', 'Trip', $trip->id);
        }

        return $trip;
    }

    /**
     * DRAFT → PUBLISHED. Requires an approved lead doctor.
     */
    public function publish(Trip $trip, ?string $idempotencyKey = null): Trip
    {
        $store = new IdempotencyStore();
        if ($idempotencyKey && $store->alreadyProcessed($idempotencyKey, 'trip.publish', $trip->id)) {
            return $trip->fresh();
        }

        $this->assertTransition($trip, TripStatus::PUBLISHED);

        if (! $trip->doctor?->isApproved()) {
            throw new RuntimeException('Trip cannot be published: lead doctor is not credentialed.', 422);
        }

        $result = $this->transition($trip, TripStatus::PUBLISHED, 'trip.published');

        if ($idempotencyKey) {
            $store->record($idempotencyKey, 'trip.publish', 'Trip', $trip->id);
        }

        return $result;
    }

    /**
     * Close a trip for new signups (PUBLISHED/FULL → CLOSED).
     */
    public function close(Trip $trip, ?string $idempotencyKey = null): Trip
    {
        $store = new IdempotencyStore();
        if ($idempotencyKey && $store->alreadyProcessed($idempotencyKey, 'trip.close', $trip->id)) {
            return $trip->fresh();
        }

        $this->assertTransition($trip, TripStatus::CLOSED);

        $result = $this->transition($trip, TripStatus::CLOSED, 'trip.closed');

        if ($idempotencyKey) {
            $store->record($idempotencyKey, 'trip.close', 'Trip', $trip->id);
        }

        return $result;
    }

    /**
     * Cancel a trip — cascades to all active signups and waitlist entries.
     */
    public function cancel(Trip $trip, User $actor, ?string $idempotencyKey = null): Trip
    {
        $store = new IdempotencyStore();
        if ($idempotencyKey && $store->alreadyProcessed($idempotencyKey, 'trip.cancel', $trip->id)) {
            return $trip->fresh();
        }

        $this->assertTransition($trip, TripStatus::CANCELLED);

        DB::transaction(function () use ($trip, $actor, $idempotencyKey, $store) {
            // Decline all waitlist entries FIRST — prevents offerNextSeat firing
            // during the hold releases below and broadcasting spurious offers
            $trip->waitlistEntries()
                ->whereIn('status', [WaitlistStatus::WAITING->value, WaitlistStatus::OFFERED->value])
                ->each(function ($entry) {
                    $entry->status = WaitlistStatus::DECLINED;
                    $entry->save();
                });

            // Release all HOLD signups (seats go back; offerNextSeat is a no-op
            // now that all waitlist entries are already DECLINED)
            $trip->signups()->where('status', SignupStatus::HOLD->value)->each(
                fn ($signup) => $this->seatService->releaseSeat($signup, HoldReleaseReason::CANCELLED)
            );

            // Cancel all CONFIRMED signups
            $trip->signups()->where('status', SignupStatus::CONFIRMED->value)->each(function ($signup) {
                $before = ['status' => $signup->status->value];
                $signup->status       = SignupStatus::CANCELLED;
                $signup->cancelled_at = now();
                $signup->saveWithLock();
                AuditService::record('trip_signup.cancelled_by_trip', 'TripSignup', $signup->id, $before, [
                    'status' => SignupStatus::CANCELLED->value,
                ]);
            });

            $this->transition($trip, TripStatus::CANCELLED, 'trip.cancelled');

            if ($idempotencyKey) {
                $store->record($idempotencyKey, 'trip.cancel', 'Trip', $trip->id);
            }
        });

        return $trip->fresh();
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function assertTransition(Trip $trip, TripStatus $to): void
    {
        if (! in_array($to, $trip->status->allowedTransitions(), true)) {
            throw new InvalidStatusTransitionException(
                from: $trip->status->value,
                to:   $to->value,
                entity: 'Trip',
            );
        }
    }

    private function transition(Trip $trip, TripStatus $newStatus, string $auditAction): Trip
    {
        $before         = ['status' => $trip->status->value];
        $trip->status   = $newStatus;
        $trip->saveWithLock();

        AuditService::record($auditAction, 'Trip', $trip->id, $before, ['status' => $newStatus->value]);

        TripStatusChanged::dispatch($trip->fresh());

        return $trip;
    }
}
