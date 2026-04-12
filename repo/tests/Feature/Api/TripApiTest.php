<?php

use App\Enums\TripStatus;
use App\Enums\UserRole;
use App\Models\Trip;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

// All mutation requests must include a same-origin Origin header so that
// VerifyApiCsrfToken grants the JSON exemption (mirrors real browser behaviour).
beforeEach(function () {
    $this->withHeaders(['Origin' => config('app.url')]);
});

// ── Helpers ───────────────────────────────────────────────────────────────────

function apiMember(): User
{
    $user = User::factory()->create();
    UserProfile::create(['user_id' => $user->id, 'first_name' => 'Test', 'last_name' => 'Member']);
    $user->addRole(UserRole::MEMBER);
    return $user->fresh();
}

function apiPublishedTrip(int $seats = 5): Trip
{
    $doctor = \App\Models\Doctor::factory()->approved()->create();
    return Trip::factory()->published()->withSeats($seats, $seats)->create([
        'lead_doctor_id' => $doctor->id,
    ]);
}

// ── GET /api/trips ─────────────────────────────────────────────────────────────

it('GET /api/trips returns paginated published trips', function () {
    $member = apiMember();
    apiPublishedTrip();
    apiPublishedTrip();
    Trip::factory()->create(['status' => TripStatus::DRAFT->value]); // must be excluded

    $this->actingAs($member)
        ->getJson('/api/trips')
        ->assertOk()
        ->assertJsonPath('total', 2); // only PUBLISHED, not DRAFT
});

it('GET /api/trips includes FULL trips (waitlist-eligible)', function () {
    $member = apiMember();
    Trip::factory()->full()->create(); // FULL trip

    $this->actingAs($member)
        ->getJson('/api/trips')
        ->assertOk()
        ->assertJsonPath('total', 1);
});

it('GET /api/trips returns 401 when unauthenticated', function () {
    $this->getJson('/api/trips')->assertUnauthorized();
});

// ── GET /api/trips/{trip} ─────────────────────────────────────────────────────

it('GET /api/trips/{trip} returns trip detail', function () {
    $member = apiMember();
    $trip   = apiPublishedTrip();

    $this->actingAs($member)
        ->getJson("/api/trips/{$trip->id}")
        ->assertOk()
        ->assertJsonPath('id', $trip->id);
});

it('GET /api/trips/{trip} returns 404 for a DRAFT trip (visibility guard)', function () {
    $member = apiMember();
    $trip   = Trip::factory()->create(['status' => TripStatus::DRAFT->value]);

    $this->actingAs($member)
        ->getJson("/api/trips/{$trip->id}")
        ->assertNotFound();
});

it('GET /api/trips/{trip} returns 404 for a CANCELLED trip', function () {
    $member = apiMember();
    $trip   = Trip::factory()->create(['status' => TripStatus::CANCELLED->value]);

    $this->actingAs($member)
        ->getJson("/api/trips/{$trip->id}")
        ->assertNotFound();
});

it('GET /api/trips/{trip} admin can see DRAFT trips', function () {
    $admin = User::factory()->create();
    UserProfile::create(['user_id' => $admin->id, 'first_name' => 'Admin', 'last_name' => 'User']);
    $admin->addRole(UserRole::ADMIN);
    $trip = Trip::factory()->create(['status' => TripStatus::DRAFT->value]);

    $this->actingAs($admin->fresh())
        ->getJson("/api/trips/{$trip->id}")
        ->assertOk()
        ->assertJsonPath('id', $trip->id);
});

// ── POST /api/trips/{trip}/hold ────────────────────────────────────────────────

it('POST /api/trips/{trip}/hold creates a HOLD signup', function () {
    $member = apiMember();
    $trip   = apiPublishedTrip(3);

    $this->actingAs($member)
        ->postJson("/api/trips/{$trip->id}/hold", [
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertCreated()
        ->assertJsonPath('status', 'HOLD');

    expect($trip->fresh()->available_seats)->toBe(2);
});

it('POST /api/trips/{trip}/hold is idempotent on same Idempotency-Key header', function () {
    $member = apiMember();
    $trip   = apiPublishedTrip(3);
    $key    = (string) Str::uuid();

    $r1 = $this->actingAs($member)
        ->postJson("/api/trips/{$trip->id}/hold", [], ['Idempotency-Key' => $key])
        ->assertCreated();

    $r2 = $this->actingAs($member)
        ->postJson("/api/trips/{$trip->id}/hold", [], ['Idempotency-Key' => $key])
        ->assertCreated();

    expect($r1->json('id'))->toBe($r2->json('id'));
    expect($trip->fresh()->available_seats)->toBe(2); // deducted only once
});

it('POST /api/trips/{trip}/hold returns 422 when trip is full', function () {
    $member = apiMember();
    $trip   = Trip::factory()->full()->create();

    $this->actingAs($member)
        ->postJson("/api/trips/{$trip->id}/hold")
        ->assertStatus(422);
});

it('POST /api/trips/{trip}/hold returns 401 when unauthenticated', function () {
    $trip = apiPublishedTrip();

    $this->postJson("/api/trips/{$trip->id}/hold")->assertUnauthorized();
});

// ── POST /api/trips/{trip}/waitlist ────────────────────────────────────────────

it('POST /api/trips/{trip}/waitlist joins waitlist for a FULL trip', function () {
    $member = apiMember();
    $trip   = Trip::factory()->full()->create();

    $this->actingAs($member)
        ->postJson("/api/trips/{$trip->id}/waitlist", [
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertCreated()
        ->assertJsonPath('status', 'WAITING');
});

it('POST /api/trips/{trip}/waitlist returns 422 when trip still has seats', function () {
    $member = apiMember();
    $trip   = apiPublishedTrip(3); // available seats > 0

    $this->actingAs($member)
        ->postJson("/api/trips/{$trip->id}/waitlist")
        ->assertStatus(422);
});
