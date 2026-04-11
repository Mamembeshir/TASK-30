<?php

use App\Enums\HoldReleaseReason;
use App\Enums\SignupStatus;
use App\Enums\TripStatus;
use App\Models\Trip;
use App\Models\TripSignup;
use App\Models\User;
use App\Services\SeatService;
use App\Services\WaitlistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

// ── holdSeat ──────────────────────────────────────────────────────────────────

it('creates a HOLD signup and decrements available_seats', function () {
    $trip = Trip::factory()->published()->withSeats(5, 5)->create();
    $user = User::factory()->create();

    $service = app(SeatService::class);
    $signup  = $service->holdSeat($trip, $user, 'idem-001');

    expect($signup->status)->toBe(SignupStatus::HOLD)
        ->and($signup->hold_expires_at)->not->toBeNull();

    expect($trip->fresh()->available_seats)->toBe(4);
});

it('transitions trip to FULL when last seat is held', function () {
    $trip = Trip::factory()->published()->withSeats(1, 1)->create();
    $user = User::factory()->create();

    app(SeatService::class)->holdSeat($trip, $user, 'idem-002');

    expect($trip->fresh()->status)->toBe(TripStatus::FULL)
        ->and($trip->fresh()->available_seats)->toBe(0);
});

it('rejects double-booking for the same trip', function () {
    $trip  = Trip::factory()->published()->withSeats(5, 5)->create();
    $user  = User::factory()->create();
    $svc   = app(SeatService::class);

    $svc->holdSeat($trip, $user, 'idem-003');

    expect(fn () => $svc->holdSeat($trip, $user, 'idem-004'))
        ->toThrow(RuntimeException::class, 'already have an active signup');
});

it('rejects hold when trip has no seats', function () {
    $trip = Trip::factory()->full()->create();
    $user = User::factory()->create();

    expect(fn () => app(SeatService::class)->holdSeat($trip, $user, 'idem-005'))
        ->toThrow(RuntimeException::class);
});

// ── confirmSeat ───────────────────────────────────────────────────────────────

it('confirms a HOLD signup', function () {
    $trip   = Trip::factory()->published()->withSeats(3, 2)->create();
    $user   = User::factory()->create();
    $signup = TripSignup::factory()->for($trip)->for($user)->create([
        'hold_expires_at' => now()->addMinutes(10),
    ]);

    app(SeatService::class)->confirmSeat($signup, Str::uuid()->toString());

    expect($signup->fresh()->status)->toBe(SignupStatus::CONFIRMED)
        ->and($signup->fresh()->confirmed_at)->not->toBeNull();
});

it('rejects confirming an expired hold', function () {
    $trip   = Trip::factory()->published()->withSeats(3, 2)->create();
    $user   = User::factory()->create();
    // HOLD status but hold_expires_at in the past (not yet processed by the expire command)
    $signup = TripSignup::factory()->for($trip)->for($user)->create([
        'hold_expires_at' => now()->subMinutes(5),
    ]);

    expect(fn () => app(SeatService::class)->confirmSeat($signup, Str::uuid()->toString()))
        ->toThrow(RuntimeException::class, 'expired');
});

// ── releaseSeat ───────────────────────────────────────────────────────────────

it('releases a seat and increments available_seats', function () {
    $trip   = Trip::factory()->published()->withSeats(3, 2)->create();
    $user   = User::factory()->create();
    $signup = TripSignup::factory()->for($trip)->for($user)->create();

    app(SeatService::class)->releaseSeat($signup, HoldReleaseReason::EXPIRED);

    expect($signup->fresh()->status)->toBe(SignupStatus::EXPIRED)
        ->and($trip->fresh()->available_seats)->toBe(3);
});

it('transitions trip from FULL back to PUBLISHED when a seat is released', function () {
    $trip   = Trip::factory()->full()->withSeats(1, 0)->create();
    $user   = User::factory()->create();
    $signup = TripSignup::factory()->for($trip)->for($user)->create();

    app(SeatService::class)->releaseSeat($signup, HoldReleaseReason::EXPIRED);

    expect($trip->fresh()->status)->toBe(TripStatus::PUBLISHED)
        ->and($trip->fresh()->available_seats)->toBe(1);
});
