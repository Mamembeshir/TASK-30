<?php

use App\Enums\SignupStatus;
use App\Enums\UserRole;
use App\Enums\WaitlistStatus;
use App\Models\Trip;
use App\Models\TripWaitlistEntry;
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

function waitlistMember(): User
{
    $user = User::factory()->create();
    UserProfile::create(['user_id' => $user->id, 'first_name' => 'Test', 'last_name' => 'User']);
    $user->addRole(UserRole::MEMBER);
    return $user->fresh();
}

// ── POST /api/waitlist/{entry}/accept ─────────────────────────────────────────

it('POST /api/waitlist/{entry}/accept returns 201 signup with status HOLD for OFFERED entry', function () {
    $user  = waitlistMember();
    $trip  = Trip::factory()->published()->withSeats(5, 1)->create();
    $entry = TripWaitlistEntry::factory()->offered()->create([
        'user_id' => $user->id,
        'trip_id' => $trip->id,
    ]);

    $this->actingAs($user)
        ->postJson("/api/waitlist/{$entry->id}/accept")
        ->assertCreated()
        ->assertJsonPath('status', SignupStatus::HOLD->value);
});

it('POST /api/waitlist/{entry}/accept returns 403 when not entry owner', function () {
    $owner = waitlistMember();
    $other = waitlistMember();
    $trip  = Trip::factory()->published()->withSeats(5, 1)->create();
    $entry = TripWaitlistEntry::factory()->offered()->create([
        'user_id' => $owner->id,
        'trip_id' => $trip->id,
    ]);

    $this->actingAs($other)
        ->postJson("/api/waitlist/{$entry->id}/accept")
        ->assertForbidden();
});

it('POST /api/waitlist/{entry}/accept returns 422 when entry is not OFFERED', function () {
    $user  = waitlistMember();
    $trip  = Trip::factory()->published()->withSeats(5, 1)->create();
    $entry = TripWaitlistEntry::factory()->create([
        'user_id' => $user->id,
        'trip_id' => $trip->id,
        'status'  => WaitlistStatus::WAITING->value,
    ]);

    $this->actingAs($user)
        ->postJson("/api/waitlist/{$entry->id}/accept")
        ->assertStatus(422);
});

// ── POST /api/waitlist/{entry}/decline ────────────────────────────────────────

it('POST /api/waitlist/{entry}/decline returns 200 with status DECLINED for OFFERED entry', function () {
    $user  = waitlistMember();
    $trip  = Trip::factory()->published()->withSeats(5, 1)->create();
    $entry = TripWaitlistEntry::factory()->offered()->create([
        'user_id' => $user->id,
        'trip_id' => $trip->id,
    ]);

    $this->actingAs($user)
        ->postJson("/api/waitlist/{$entry->id}/decline")
        ->assertOk()
        ->assertJsonPath('status', WaitlistStatus::DECLINED->value);
});

it('POST /api/waitlist/{entry}/decline returns 403 when not entry owner', function () {
    $owner = waitlistMember();
    $other = waitlistMember();
    $trip  = Trip::factory()->published()->withSeats(5, 1)->create();
    $entry = TripWaitlistEntry::factory()->offered()->create([
        'user_id' => $owner->id,
        'trip_id' => $trip->id,
    ]);

    $this->actingAs($other)
        ->postJson("/api/waitlist/{$entry->id}/decline")
        ->assertForbidden();
});

it('POST /api/waitlist/{entry}/decline returns 422 when entry is not OFFERED', function () {
    $user  = waitlistMember();
    $trip  = Trip::factory()->published()->withSeats(5, 1)->create();
    $entry = TripWaitlistEntry::factory()->create([
        'user_id' => $user->id,
        'trip_id' => $trip->id,
        'status'  => WaitlistStatus::WAITING->value,
    ]);

    $this->actingAs($user)
        ->postJson("/api/waitlist/{$entry->id}/decline")
        ->assertStatus(422);
});
