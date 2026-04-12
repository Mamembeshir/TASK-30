<?php

use App\Enums\SignupStatus;
use App\Enums\TripStatus;
use App\Enums\UserStatus;
use App\Livewire\Trips\MySignups;
use App\Livewire\Trips\TripDetail;
use App\Livewire\Trips\TripList;
use App\Models\Doctor;
use App\Models\Trip;
use App\Models\TripSignup;
use App\Models\User;
use App\Models\UserProfile;
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

it('renders lead physician full name from profile', function () {
    $member      = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $doctorUser  = User::factory()->create(['status' => UserStatus::ACTIVE]);
    UserProfile::create([
        'user_id'    => $doctorUser->id,
        'first_name' => 'Ada',
        'last_name'  => 'Lovelace',
    ]);
    $doctor = Doctor::factory()->approved()->create(['user_id' => $doctorUser->id]);
    $trip   = Trip::factory()->published()->withSeats(5, 5)->create(['lead_doctor_id' => $doctor->id]);

    Livewire::actingAs($member)
        ->test(TripDetail::class, ['trip' => $trip])
        ->assertSee('Ada Lovelace');
});

it('shows job title fallback to username when profile has no name', function () {
    $member     = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $doctorUser = User::factory()->create(['status' => UserStatus::ACTIVE, 'username' => 'dr_smith']);
    // No UserProfile created — fullName() not available; falls back to username.
    $doctor = Doctor::factory()->approved()->create(['user_id' => $doctorUser->id]);
    $trip   = Trip::factory()->published()->withSeats(5, 5)->create(['lead_doctor_id' => $doctor->id]);

    Livewire::actingAs($member)
        ->test(TripDetail::class, ['trip' => $trip])
        ->assertSee('dr_smith');
});


it('shows join waitlist button when trip is FULL', function () {
    $user = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $trip = Trip::factory()->full()->create();

    Livewire::actingAs($user)
        ->test(TripDetail::class, ['trip' => $trip])
        ->assertSee('Join Waitlist');
});

// Trips not in PUBLISHED/FULL status must not be discoverable by members — a
// guessed or scraped UUID for a DRAFT/CLOSED/CANCELLED trip must return 404
// (not 403) so that the response does not confirm the trip's existence.

it('returns 404 when a member accesses a DRAFT trip by direct URL', function () {
    $user = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $trip = Trip::factory()->create(['status' => TripStatus::DRAFT->value]);

    Livewire::actingAs($user)
        ->test(TripDetail::class, ['trip' => $trip])
        ->assertNotFound();
});

it('returns 404 when a member accesses a CANCELLED trip by direct URL', function () {
    $user = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $trip = Trip::factory()->create(['status' => TripStatus::CANCELLED->value]);

    Livewire::actingAs($user)
        ->test(TripDetail::class, ['trip' => $trip])
        ->assertNotFound();
});

it('returns 404 when a member accesses a CLOSED trip by direct URL', function () {
    $user = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $trip = Trip::factory()->create(['status' => TripStatus::CLOSED->value]);

    Livewire::actingAs($user)
        ->test(TripDetail::class, ['trip' => $trip])
        ->assertNotFound();
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
