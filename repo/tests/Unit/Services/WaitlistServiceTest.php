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

it('rejects duplicate waitlist entry for same user and trip', function () {
    $trip = Trip::factory()->full()->create();
    $user = User::factory()->create();
    $svc  = app(WaitlistService::class);

    $svc->joinWaitlist($trip, $user);

    expect(fn () => $svc->joinWaitlist($trip, $user))
        ->toThrow(RuntimeException::class, 'already on the waitlist');
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
