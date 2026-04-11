<?php

use App\Enums\SignupStatus;
use App\Enums\TripStatus;
use App\Enums\UserStatus;
use App\Livewire\Trips\MySignups;
use App\Livewire\Trips\TripDetail;
use App\Livewire\Trips\TripList;
use App\Models\Trip;
use App\Models\TripSignup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ── TripList ──────────────────────────────────────────────────────────────────

it('renders the trip list', function () {
    $user = User::factory()->create(['status' => UserStatus::ACTIVE]);
    Trip::factory()->published()->count(3)->create();

    Livewire::actingAs($user)
        ->test(TripList::class)
        ->assertOk()
        ->assertSee('seats');
});

it('filters trips by search term', function () {
    $user = User::factory()->create(['status' => UserStatus::ACTIVE]);
    Trip::factory()->published()->create(['title' => 'Eye Surgery in Cairo']);
    Trip::factory()->published()->create(['title' => 'Heart Surgery in London']);

    Livewire::actingAs($user)
        ->test(TripList::class)
        ->set('search', 'Cairo')
        ->assertSee('Cairo')
        ->assertDontSee('London');
});

// ── TripDetail ────────────────────────────────────────────────────────────────

it('renders trip detail for a published trip', function () {
    $user = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $trip = Trip::factory()->published()->withSeats(5, 5)->create();

    Livewire::actingAs($user)
        ->test(TripDetail::class, ['trip' => $trip])
        ->assertOk()
        ->assertSee($trip->title)
        ->assertSee('Book a Seat');
});

it('shows join waitlist button when trip is FULL', function () {
    $user = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $trip = Trip::factory()->full()->create();

    Livewire::actingAs($user)
        ->test(TripDetail::class, ['trip' => $trip])
        ->assertSee('Join Waitlist');
});

it('creates a seat hold via holdSeat action', function () {
    $user = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $trip = Trip::factory()->published()->withSeats(5, 5)->create();

    Livewire::actingAs($user)
        ->test(TripDetail::class, ['trip' => $trip])
        ->call('holdSeat')
        ->assertRedirect();

    expect(TripSignup::where('trip_id', $trip->id)->where('user_id', $user->id)->exists())->toBeTrue();
    expect($trip->fresh()->available_seats)->toBe(4);
});

// ── MySignups ─────────────────────────────────────────────────────────────────

it('renders my signups list', function () {
    $user  = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $trip  = Trip::factory()->published()->create();
    $signup = TripSignup::factory()->for($trip)->for($user)->confirmed()->create();

    Livewire::actingAs($user)
        ->test(MySignups::class)
        ->assertOk()
        ->assertSee($trip->title)
        ->assertSee('Confirmed');
});

it('allows cancelling a confirmed signup', function () {
    $user   = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $trip   = Trip::factory()->published()->withSeats(3, 2)->create();
    $signup = TripSignup::factory()->for($trip)->for($user)->confirmed()->create();

    Livewire::actingAs($user)
        ->test(MySignups::class)
        ->call('cancelSignup', $signup->id);

    expect($signup->fresh()->status)->toBe(SignupStatus::CANCELLED);
});
