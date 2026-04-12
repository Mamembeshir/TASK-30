<?php

use App\Enums\SignupStatus;
use App\Enums\TenderType;
use App\Enums\UserRole;
use App\Models\Trip;
use App\Models\TripSignup;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// All mutation requests must include a same-origin Origin header so that
// VerifyApiCsrfToken grants the JSON exemption (mirrors real browser behaviour).
beforeEach(function () {
    $this->withHeaders(['Origin' => config('app.url')]);
});

// ── Helpers ───────────────────────────────────────────────────────────────────

function signupMember(): User
{
    $user = User::factory()->create();
    UserProfile::create(['user_id' => $user->id, 'first_name' => 'Test', 'last_name' => 'User']);
    $user->addRole(UserRole::MEMBER);
    return $user->fresh();
}

// ── POST /api/signups/{signup}/payment ────────────────────────────────────────

it('POST /api/signups/{signup}/payment returns 200 with signup and payment for owner', function () {
    $user   = signupMember();
    $trip   = Trip::factory()->published()->withSeats(5, 4)->create(['price_cents' => 10000]);
    $signup = TripSignup::factory()->create([
        'user_id'          => $user->id,
        'trip_id'          => $trip->id,
        'status'           => SignupStatus::HOLD->value,
        'hold_expires_at'  => now()->addMinutes(10),
    ]);

    $this->actingAs($user)
        ->postJson("/api/signups/{$signup->id}/payment", [
            'tender_type' => TenderType::CASH->value,
        ])
        ->assertOk()
        ->assertJsonStructure(['signup', 'payment']);
});

it('POST /api/signups/{signup}/payment returns 403 when caller does not own signup', function () {
    $owner  = signupMember();
    $other  = signupMember();
    $trip   = Trip::factory()->published()->withSeats(5, 4)->create(['price_cents' => 10000]);
    $signup = TripSignup::factory()->create([
        'user_id'         => $owner->id,
        'trip_id'         => $trip->id,
        'status'          => SignupStatus::HOLD->value,
        'hold_expires_at' => now()->addMinutes(10),
    ]);

    $this->actingAs($other)
        ->postJson("/api/signups/{$signup->id}/payment", [
            'tender_type' => TenderType::CASH->value,
        ])
        ->assertForbidden();
});

it('POST /api/signups/{signup}/payment returns 422 when signup is not in HOLD status', function () {
    $user   = signupMember();
    $trip   = Trip::factory()->published()->withSeats(5, 4)->create(['price_cents' => 10000]);
    $signup = TripSignup::factory()->confirmed()->create([
        'user_id' => $user->id,
        'trip_id' => $trip->id,
    ]);

    $this->actingAs($user)
        ->postJson("/api/signups/{$signup->id}/payment", [
            'tender_type' => TenderType::CASH->value,
        ])
        ->assertStatus(422);
});

it('POST /api/signups/{signup}/payment returns 422 when hold is expired', function () {
    $user   = signupMember();
    $trip   = Trip::factory()->published()->withSeats(5, 4)->create(['price_cents' => 10000]);
    $signup = TripSignup::factory()->expired()->create([
        'user_id' => $user->id,
        'trip_id' => $trip->id,
    ]);

    $this->actingAs($user)
        ->postJson("/api/signups/{$signup->id}/payment", [
            'tender_type' => TenderType::CASH->value,
        ])
        ->assertStatus(422);
});

it('POST /api/signups/{signup}/payment returns 422 on missing tender_type', function () {
    $user   = signupMember();
    $trip   = Trip::factory()->published()->withSeats(5, 4)->create(['price_cents' => 10000]);
    $signup = TripSignup::factory()->create([
        'user_id'         => $user->id,
        'trip_id'         => $trip->id,
        'status'          => SignupStatus::HOLD->value,
        'hold_expires_at' => now()->addMinutes(10),
    ]);

    $this->actingAs($user)
        ->postJson("/api/signups/{$signup->id}/payment", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('tender_type');
});

// ── POST /api/signups/{signup}/cancel ─────────────────────────────────────────

it('POST /api/signups/{signup}/cancel cancels a HOLD signup', function () {
    $user   = signupMember();
    $trip   = Trip::factory()->published()->withSeats(5, 4)->create();
    $signup = TripSignup::factory()->create([
        'user_id'         => $user->id,
        'trip_id'         => $trip->id,
        'status'          => SignupStatus::HOLD->value,
        'hold_expires_at' => now()->addMinutes(10),
    ]);

    $this->actingAs($user)
        ->postJson("/api/signups/{$signup->id}/cancel")
        ->assertOk()
        ->assertJsonPath('status', SignupStatus::CANCELLED->value);
});

it('POST /api/signups/{signup}/cancel cancels a CONFIRMED signup', function () {
    $user   = signupMember();
    $trip   = Trip::factory()->published()->withSeats(5, 4)->create();
    $signup = TripSignup::factory()->confirmed()->create([
        'user_id' => $user->id,
        'trip_id' => $trip->id,
    ]);

    $this->actingAs($user)
        ->postJson("/api/signups/{$signup->id}/cancel")
        ->assertOk()
        ->assertJsonPath('status', SignupStatus::CANCELLED->value);
});

it('POST /api/signups/{signup}/cancel returns 403 when caller does not own signup', function () {
    $owner  = signupMember();
    $other  = signupMember();
    $trip   = Trip::factory()->published()->withSeats(5, 4)->create();
    $signup = TripSignup::factory()->create([
        'user_id'         => $owner->id,
        'trip_id'         => $trip->id,
        'status'          => SignupStatus::HOLD->value,
        'hold_expires_at' => now()->addMinutes(10),
    ]);

    $this->actingAs($other)
        ->postJson("/api/signups/{$signup->id}/cancel")
        ->assertForbidden();
});

it('POST /api/signups/{signup}/cancel returns 422 when signup is already cancelled', function () {
    $user   = signupMember();
    $trip   = Trip::factory()->published()->withSeats(5, 4)->create();
    $signup = TripSignup::factory()->cancelled()->create([
        'user_id' => $user->id,
        'trip_id' => $trip->id,
    ]);

    $this->actingAs($user)
        ->postJson("/api/signups/{$signup->id}/cancel")
        ->assertStatus(422);
});
