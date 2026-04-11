<?php

use App\Enums\WaitlistStatus;
use App\Models\Trip;
use App\Models\TripWaitlistEntry;
use App\Models\User;
use App\Services\WaitlistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('joins the waitlist and assigns position 1 when empty', function () {
    $trip  = Trip::factory()->full()->create();
    $user  = User::factory()->create();

    $entry = app(WaitlistService::class)->joinWaitlist($trip, $user, Str::uuid()->toString());

    expect($entry->status)->toBe(WaitlistStatus::WAITING)
        ->and($entry->position)->toBe(1);
});

// FIN audit Issue 3 — gate: waitlist is a full-trip construct. Joining
// while seats are still available is a bug, not a UX convenience, and the
// service must refuse it outright so callers are forced down the normal
// holdSeat path instead of silently queueing.
it('rejects joinWaitlist when the trip still has available seats', function () {
    $trip = Trip::factory()->published()->withSeats(5, 3)->create();
    $user = User::factory()->create();

    expect(fn () => app(WaitlistService::class)->joinWaitlist($trip, $user, Str::uuid()->toString()))
        ->toThrow(\RuntimeException::class, 'seats are still available');
});

it('rejects joinWaitlist on a CANCELLED trip', function () {
    $trip = Trip::factory()->state([
        'status'          => \App\Enums\TripStatus::CANCELLED->value,
        'available_seats' => 0,
    ])->create();
    $user = User::factory()->create();

    expect(fn () => app(WaitlistService::class)->joinWaitlist($trip, $user, Str::uuid()->toString()))
        ->toThrow(\RuntimeException::class, 'CANCELLED');
});

it('allows joinWaitlist idempotent retry even if the trip has since reopened', function () {
    // A user joined while the trip was FULL, then a seat freed up. Their
    // existing entry must still be reachable via its idempotency key —
    // the capacity gate only applies to *new* entries.
    $trip = Trip::factory()->full()->create();
    $user = User::factory()->create();
    $key  = 'waitlist-' . $user->id . '-' . $trip->id;

    $first = app(WaitlistService::class)->joinWaitlist($trip, $user, $key);

    // Trip reopens: seats become available and status moves back to PUBLISHED.
    $trip->update([
        'status'          => \App\Enums\TripStatus::PUBLISHED->value,
        'available_seats' => 2,
    ]);

    $second = app(WaitlistService::class)->joinWaitlist($trip->fresh(), $user, $key);
    expect($second->id)->toBe($first->id);
});

it('assigns sequential positions', function () {
    $trip  = Trip::factory()->full()->create();
    $svc   = app(WaitlistService::class);

    $u1 = User::factory()->create();
    $u2 = User::factory()->create();

    $e1 = $svc->joinWaitlist($trip, $u1, Str::uuid()->toString());
    $e2 = $svc->joinWaitlist($trip, $u2, Str::uuid()->toString());

    expect($e1->position)->toBe(1)
        ->and($e2->position)->toBe(2);
});

it('is idempotent on the caller key: re-joining with the same key returns the existing entry', function () {
    // Universal service-layer idempotency contract: a retry with the same
    // caller-stable idempotency key must return the exact same entry row
    // instead of creating a duplicate or throwing. This is the same contract
    // SeatService::holdSeat, MembershipService::purchase, etc. follow.
    $trip = Trip::factory()->full()->create();
    $user = User::factory()->create();
    $svc  = app(WaitlistService::class);
    $key  = 'waitlist-' . $user->id . '-' . $trip->id;

    $first  = $svc->joinWaitlist($trip, $user, $key);
    $second = $svc->joinWaitlist($trip, $user, $key);

    expect($second->id)->toBe($first->id)
        ->and($second->position)->toBe($first->position)
        ->and($second->idempotency_key)->toBe($key);

    expect(TripWaitlistEntry::where('trip_id', $trip->id)
        ->where('user_id', $user->id)->count())->toBe(1);
});

it('natural-key safety net: a second call with a different key still returns the existing entry', function () {
    // Defense in depth: if the same user somehow lands here with a *different*
    // key (e.g. key derivation changed between deploys, or a legacy row exists
    // without a key), the (trip_id, user_id) unique constraint would otherwise
    // blow up the insert. joinWaitlist detects the existing row via the natural
    // key and returns it instead of crashing.
    $trip = Trip::factory()->full()->create();
    $user = User::factory()->create();
    $svc  = app(WaitlistService::class);

    $first  = $svc->joinWaitlist($trip, $user, 'first-key');
    $second = $svc->joinWaitlist($trip, $user, 'second-different-key');

    expect($second->id)->toBe($first->id);
    // The original row's key is preserved — we don't overwrite it.
    expect($second->idempotency_key)->toBe('first-key');

    expect(TripWaitlistEntry::where('trip_id', $trip->id)
        ->where('user_id', $user->id)->count())->toBe(1);
});

it('offers seat to first WAITING entry', function () {
    $trip  = Trip::factory()->published()->withSeats(3, 1)->create();
    $user  = User::factory()->create();
    $entry = TripWaitlistEntry::factory()->for($trip)->for($user)->create(['position' => 1]);

    app(WaitlistService::class)->offerNextSeat($trip);

    expect($entry->fresh()->status)->toBe(WaitlistStatus::OFFERED)
        ->and($entry->fresh()->offer_expires_at)->not->toBeNull();
});

it('expires an offer and cascades to next entry', function () {
    $trip  = Trip::factory()->published()->withSeats(3, 1)->create();
    $u1    = User::factory()->create();
    $u2    = User::factory()->create();
    $e1    = TripWaitlistEntry::factory()->for($trip)->for($u1)->offered()->create(['position' => 1]);
    $e2    = TripWaitlistEntry::factory()->for($trip)->for($u2)->create(['position' => 2]);

    app(WaitlistService::class)->expireOffer($e1, 'waitlist.expire.' . $e1->id);

    expect($e1->fresh()->status)->toBe(WaitlistStatus::EXPIRED)
        ->and($e2->fresh()->status)->toBe(WaitlistStatus::OFFERED);
});

it('declines an offer and cascades to next entry', function () {
    $trip  = Trip::factory()->published()->withSeats(3, 1)->create();
    $u1    = User::factory()->create();
    $u2    = User::factory()->create();
    $e1    = TripWaitlistEntry::factory()->for($trip)->for($u1)->offered()->create(['position' => 1]);
    $e2    = TripWaitlistEntry::factory()->for($trip)->for($u2)->create(['position' => 2]);

    app(WaitlistService::class)->declineOffer($e1, 'waitlist.decline.' . $e1->id);

    expect($e1->fresh()->status)->toBe(WaitlistStatus::DECLINED)
        ->and($e2->fresh()->status)->toBe(WaitlistStatus::OFFERED);
});
