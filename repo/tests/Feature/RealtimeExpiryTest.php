<?php

use App\Enums\HoldReleaseReason;
use App\Enums\SignupStatus;
use App\Enums\TripStatus;
use App\Enums\WaitlistStatus;
use App\Events\HoldExpiring;
use App\Jobs\ExpireWaitlistOfferJob;
use App\Jobs\NotifyHoldExpiring;
use App\Jobs\ReleaseExpiredHold;
use App\Models\Trip;
use App\Models\TripSignup;
use App\Models\TripWaitlistEntry;
use App\Models\User;
use App\Services\SeatService;
use App\Services\WaitlistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Real-time expiry (delayed queue jobs)
|--------------------------------------------------------------------------
|
| These tests pin the "WebSocket + delayed queue job" replacement for the
| old polling-based expiry. See docs/questions.md §1.1 and §1.4.
|
| We use Bus::fake() to intercept the dispatches and assert on the delay
| argument. The job *handlers* themselves are also exercised to prove the
| idempotency guards work (so the 10-minute cron safety-net can't
| double-release a hold that's already confirmed, etc.).
|
*/

// ── holdSeat dispatches both delayed jobs ─────────────────────────────────────

it('holdSeat queues NotifyHoldExpiring at T-2min and ReleaseExpiredHold at expiry', function () {
    Bus::fake([NotifyHoldExpiring::class, ReleaseExpiredHold::class]);

    // Freeze time so the delay comparison is deterministic
    $frozen = now()->startOfSecond();
    \Illuminate\Support\Carbon::setTestNow($frozen);

    $trip = Trip::factory()->published()->withSeats(5, 5)->create();
    $user = User::factory()->create();

    $signup = app(SeatService::class)->holdSeat($trip, $user, 'idem-realtime-1');

    $holdMinutes = (int) config('medvoyage.seat_hold_minutes', 10);
    $expectedExpiry = $frozen->copy()->addMinutes($holdMinutes);
    $expectedWarn   = $expectedExpiry->copy()->subMinutes(2);

    Bus::assertDispatched(
        ReleaseExpiredHold::class,
        function (ReleaseExpiredHold $job) use ($signup, $expectedExpiry) {
            return $job->signupId === $signup->id
                && $job->delay instanceof \DateTimeInterface
                && (int) $job->delay->getTimestamp() === (int) $expectedExpiry->getTimestamp();
        }
    );

    Bus::assertDispatched(
        NotifyHoldExpiring::class,
        function (NotifyHoldExpiring $job) use ($signup, $expectedWarn) {
            return $job->signupId === $signup->id
                && $job->delay instanceof \DateTimeInterface
                && (int) $job->delay->getTimestamp() === (int) $expectedWarn->getTimestamp();
        }
    );

    \Illuminate\Support\Carbon::setTestNow();
});

it('holdSeat skips the T-2min warning job when hold window is shorter than 2 minutes', function () {
    // With seat_hold_minutes=1 there is no meaningful "2 minutes remaining"
    // warning — the warning timestamp is in the past, so dispatch is skipped.
    config(['medvoyage.seat_hold_minutes' => 1]);
    Bus::fake([NotifyHoldExpiring::class, ReleaseExpiredHold::class]);

    $trip = Trip::factory()->published()->withSeats(5, 5)->create();
    $user = User::factory()->create();

    app(SeatService::class)->holdSeat($trip, $user, 'idem-realtime-2');

    Bus::assertNotDispatched(NotifyHoldExpiring::class);
    Bus::assertDispatched(ReleaseExpiredHold::class, 1);
});

// ── offerNextSeat dispatches the expire job ───────────────────────────────────

it('offerNextSeat queues ExpireWaitlistOfferJob delayed to offer_expires_at', function () {
    Bus::fake([ExpireWaitlistOfferJob::class]);

    $frozen = now()->startOfSecond();
    \Illuminate\Support\Carbon::setTestNow($frozen);

    $trip  = Trip::factory()->published()->withSeats(1, 1)->create();
    $user  = User::factory()->create();
    $entry = TripWaitlistEntry::factory()->for($trip)->for($user)->create([
        'status'   => WaitlistStatus::WAITING->value,
        'position' => 1,
    ]);

    app(WaitlistService::class)->offerNextSeat($trip);

    $offerMinutes   = (int) config('medvoyage.waitlist_offer_minutes', 10);
    $expectedExpiry = $frozen->copy()->addMinutes($offerMinutes);

    Bus::assertDispatched(
        ExpireWaitlistOfferJob::class,
        function (ExpireWaitlistOfferJob $job) use ($entry, $expectedExpiry) {
            return $job->waitlistEntryId === $entry->id
                && $job->delay instanceof \DateTimeInterface
                && (int) $job->delay->getTimestamp() === (int) $expectedExpiry->getTimestamp();
        }
    );

    \Illuminate\Support\Carbon::setTestNow();
});

// ── Job handlers: idempotency + broadcast ─────────────────────────────────────

it('ReleaseExpiredHold releases a still-HOLD signup when its expiry has passed', function () {
    $trip   = Trip::factory()->published()->withSeats(5, 4)->create();
    $user   = User::factory()->create();
    $signup = TripSignup::factory()->for($trip)->for($user)->create([
        'status'          => SignupStatus::HOLD->value,
        'hold_expires_at' => now()->subSecond(),
    ]);

    (new ReleaseExpiredHold($signup->id))->handle(app(SeatService::class));

    expect($signup->fresh()->status)->toBe(SignupStatus::EXPIRED);
    expect($trip->fresh()->available_seats)->toBe(5);
});

it('ReleaseExpiredHold no-ops when the signup is already confirmed (idempotent)', function () {
    $trip   = Trip::factory()->published()->withSeats(5, 4)->create();
    $user   = User::factory()->create();
    $signup = TripSignup::factory()->for($trip)->for($user)->create([
        'status'          => SignupStatus::CONFIRMED->value,
        'hold_expires_at' => now()->subSecond(),
    ]);

    (new ReleaseExpiredHold($signup->id))->handle(app(SeatService::class));

    // Status unchanged, seat not re-released
    expect($signup->fresh()->status)->toBe(SignupStatus::CONFIRMED);
    expect($trip->fresh()->available_seats)->toBe(4);
});

it('ReleaseExpiredHold no-ops when the signup was hard-deleted', function () {
    $missingId = (string) \Illuminate\Support\Str::uuid();

    // Should not throw
    (new ReleaseExpiredHold($missingId))->handle(app(SeatService::class));

    expect(true)->toBeTrue();
});

it('NotifyHoldExpiring dispatches the HoldExpiring broadcast event', function () {
    Event::fake([HoldExpiring::class]);

    $trip   = Trip::factory()->published()->withSeats(5, 4)->create();
    $user   = User::factory()->create();
    $signup = TripSignup::factory()->for($trip)->for($user)->create([
        'status'          => SignupStatus::HOLD->value,
        'hold_expires_at' => now()->addMinutes(2),
    ]);

    (new NotifyHoldExpiring($signup->id))->handle();

    Event::assertDispatched(
        HoldExpiring::class,
        fn (HoldExpiring $e) => $e->signup->id === $signup->id,
    );
});

it('NotifyHoldExpiring does not broadcast if the hold was already confirmed', function () {
    Event::fake([HoldExpiring::class]);

    $trip   = Trip::factory()->published()->withSeats(5, 4)->create();
    $user   = User::factory()->create();
    $signup = TripSignup::factory()->for($trip)->for($user)->create([
        'status'          => SignupStatus::CONFIRMED->value,
        'hold_expires_at' => now()->addMinutes(2),
    ]);

    (new NotifyHoldExpiring($signup->id))->handle();

    Event::assertNotDispatched(HoldExpiring::class);
});

it('ExpireWaitlistOfferJob expires an OFFERED entry', function () {
    $trip  = Trip::factory()->published()->withSeats(1, 0)->create();
    $user  = User::factory()->create();
    $entry = TripWaitlistEntry::factory()->for($trip)->for($user)->create([
        'status'           => WaitlistStatus::OFFERED->value,
        'offer_expires_at' => now()->subSecond(),
        'position'         => 1,
    ]);

    (new ExpireWaitlistOfferJob($entry->id))->handle(app(WaitlistService::class));

    expect($entry->fresh()->status)->toBe(WaitlistStatus::EXPIRED);
});

it('ExpireWaitlistOfferJob no-ops when the entry was already accepted', function () {
    $trip  = Trip::factory()->published()->withSeats(1, 0)->create();
    $user  = User::factory()->create();
    $entry = TripWaitlistEntry::factory()->for($trip)->for($user)->create([
        'status'           => WaitlistStatus::ACCEPTED->value,
        'offer_expires_at' => now()->subSecond(),
        'position'         => 1,
    ]);

    (new ExpireWaitlistOfferJob($entry->id))->handle(app(WaitlistService::class));

    expect($entry->fresh()->status)->toBe(WaitlistStatus::ACCEPTED);
});
