<?php

use App\Enums\WaitlistStatus;
use App\Models\Trip;
use App\Models\TripWaitlistEntry;
use App\Models\User;
use App\Services\WaitlistService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('joins the waitlist and assigns position 1 when empty', function () {
    $trip  = Trip::factory()->full()->create();
    $user  = User::factory()->create();

    $entry = app(WaitlistService::class)->joinWaitlist($trip, $user);

    expect($entry->status)->toBe(WaitlistStatus::WAITING)
        ->and($entry->position)->toBe(1);
});

it('assigns sequential positions', function () {
    $trip  = Trip::factory()->full()->create();
    $svc   = app(WaitlistService::class);

    $u1 = User::factory()->create();
    $u2 = User::factory()->create();

    $e1 = $svc->joinWaitlist($trip, $u1);
    $e2 = $svc->joinWaitlist($trip, $u2);

    expect($e1->position)->toBe(1)
        ->and($e2->position)->toBe(2);
});

it('is idempotent: re-joining returns the existing entry instead of creating a second one', function () {
    // joinWaitlist was changed from throw-on-duplicate to return-existing so
    // that double-clicks and retries are safe under the universal service-layer
    // idempotency contract. The test pins that behavior: the second call must
    // return the *same* entry row (same id, same position) and must not create
    // a second database row for the same (trip, user) pair.
    $trip = Trip::factory()->full()->create();
    $user = User::factory()->create();
    $svc  = app(WaitlistService::class);

    $first  = $svc->joinWaitlist($trip, $user);
    $second = $svc->joinWaitlist($trip, $user);

    expect($second->id)->toBe($first->id)
        ->and($second->position)->toBe($first->position);

    expect(\App\Models\TripWaitlistEntry::where('trip_id', $trip->id)
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

    app(WaitlistService::class)->expireOffer($e1);

    expect($e1->fresh()->status)->toBe(WaitlistStatus::EXPIRED)
        ->and($e2->fresh()->status)->toBe(WaitlistStatus::OFFERED);
});

it('declines an offer and cascades to next entry', function () {
    $trip  = Trip::factory()->published()->withSeats(3, 1)->create();
    $u1    = User::factory()->create();
    $u2    = User::factory()->create();
    $e1    = TripWaitlistEntry::factory()->for($trip)->for($u1)->offered()->create(['position' => 1]);
    $e2    = TripWaitlistEntry::factory()->for($trip)->for($u2)->create(['position' => 2]);

    app(WaitlistService::class)->declineOffer($e1);

    expect($e1->fresh()->status)->toBe(WaitlistStatus::DECLINED)
        ->and($e2->fresh()->status)->toBe(WaitlistStatus::OFFERED);
});
