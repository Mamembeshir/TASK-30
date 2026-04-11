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
     */
    public function create(array $data, User $creator): Trip
    {
        $trip = Trip::create(array_merge($data, [
            'status'          => TripStatus::DRAFT->value,
            'available_seats' => $data['total_seats'],
            'booking_count'   => 0,
            'created_by'      => $creator->id,
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
    public function update(Trip $trip, array $data): Trip
    {
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

        return $trip;
    }

    /**
     * DRAFT → PUBLISHED. Requires an approved lead doctor.
     */
    public function publish(Trip $trip): Trip
    {
        $this->assertTransition($trip, TripStatus::PUBLISHED);

        if (! $trip->doctor?->isApproved()) {
            throw new RuntimeException('Trip cannot be published: lead doctor is not credentialed.', 422);
        }

        return $this->transition($trip, TripStatus::PUBLISHED, 'trip.published');
    }

    /**
     * Close a trip for new signups (PUBLISHED/FULL → CLOSED).
     */
    public function close(Trip $trip): Trip
    {
        $this->assertTransition($trip, TripStatus::CLOSED);

        return $this->transition($trip, TripStatus::CLOSED, 'trip.closed');
    }

    /**
     * Cancel a trip — cascades to all active signups and waitlist entries.
     */
    public function cancel(Trip $trip, User $actor): Trip
    {
        $this->assertTransition($trip, TripStatus::CANCELLED);

        DB::transaction(function () use ($trip, $actor) {
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
