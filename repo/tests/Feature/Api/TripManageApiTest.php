<?php

use App\Enums\TripDifficulty;
use App\Enums\TripStatus;
use App\Enums\UserRole;
use App\Models\Doctor;
use App\Models\Trip;
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

function tripAdminUser(): User
{
    $user = User::factory()->create();
    UserProfile::create(['user_id' => $user->id, 'first_name' => 'Admin', 'last_name' => 'User']);
    $user->addRole(UserRole::ADMIN);
    return $user->fresh();
}

function tripNonAdminUser(): User
{
    $user = User::factory()->create();
    UserProfile::create(['user_id' => $user->id, 'first_name' => 'Plain', 'last_name' => 'Member']);
    $user->addRole(UserRole::MEMBER);
    return $user->fresh();
}

function validTripPayload(Doctor $doctor): array
{
    return [
        'title'            => 'Cardiology Mission to Nairobi',
        'lead_doctor_id'   => $doctor->id,
        'specialty'        => 'Cardiology',
        'destination'      => 'Nairobi, Kenya',
        'start_date'       => now()->addDays(30)->toDateString(),
        'end_date'         => now()->addDays(37)->toDateString(),
        'difficulty_level' => TripDifficulty::MODERATE->value,
        'total_seats'      => 20,
        'price_cents'      => 150000,
    ];
}

// ── POST /api/admin/trips ────────────────────────────────────────────────────

it('POST /api/admin/trips admin creates a DRAFT trip and returns 201', function () {
    $admin  = tripAdminUser();
    $doctor = Doctor::factory()->approved()->create();

    $this->actingAs($admin)
        ->postJson('/api/admin/trips', validTripPayload($doctor))
        ->assertCreated()
        ->assertJsonPath('status', TripStatus::DRAFT->value);
});

it('POST /api/admin/trips returns 403 for non-admin', function () {
    $member = tripNonAdminUser();
    $doctor = Doctor::factory()->approved()->create();

    $this->actingAs($member)
        ->postJson('/api/admin/trips', validTripPayload($doctor))
        ->assertForbidden();
});

it('POST /api/admin/trips returns 422 on missing required fields (empty body)', function () {
    $admin = tripAdminUser();

    $this->actingAs($admin)
        ->postJson('/api/admin/trips', [])
        ->assertStatus(422);
});

it('POST /api/admin/trips returns 422 when start_date is in the past', function () {
    $admin  = tripAdminUser();
    $doctor = Doctor::factory()->approved()->create();

    $payload                = validTripPayload($doctor);
    $payload['start_date']  = now()->subDay()->toDateString();
    $payload['end_date']    = now()->toDateString();

    $this->actingAs($admin)
        ->postJson('/api/admin/trips', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors('start_date');
});

// ── PUT /api/admin/trips/{trip} ───────────────────────────────────────────────

it('PUT /api/admin/trips/{trip} admin updates DRAFT trip title and returns 200', function () {
    $admin = tripAdminUser();
    $trip  = Trip::factory()->create(['status' => TripStatus::DRAFT->value]);

    $this->actingAs($admin)
        ->putJson("/api/admin/trips/{$trip->id}", [
            'title' => 'Updated Mission Title',
        ])
        ->assertOk()
        ->assertJsonPath('title', 'Updated Mission Title');
});

it('PUT /api/admin/trips/{trip} returns 403 for non-admin', function () {
    $member = tripNonAdminUser();
    $trip   = Trip::factory()->create(['status' => TripStatus::DRAFT->value]);

    $this->actingAs($member)
        ->putJson("/api/admin/trips/{$trip->id}", [
            'title' => 'Should Not Update',
        ])
        ->assertForbidden();
});

it('PUT /api/admin/trips/{trip} returns 422 when trip is not DRAFT', function () {
    $admin = tripAdminUser();
    $trip  = Trip::factory()->published()->create();

    $this->actingAs($admin)
        ->putJson("/api/admin/trips/{$trip->id}", [
            'title' => 'Cannot Update Published Trip',
        ])
        ->assertStatus(422);
});

// ── POST /api/admin/trips/{trip}/publish ─────────────────────────────────────

it('POST /api/admin/trips/{trip}/publish transitions DRAFT trip with approved doctor to PUBLISHED', function () {
    $admin  = tripAdminUser();
    $doctor = Doctor::factory()->approved()->create();
    $trip   = Trip::factory()->create([
        'status'         => TripStatus::DRAFT->value,
        'lead_doctor_id' => $doctor->id,
    ]);

    $this->actingAs($admin)
        ->postJson("/api/admin/trips/{$trip->id}/publish")
        ->assertOk()
        ->assertJsonPath('status', TripStatus::PUBLISHED->value);
});

it('POST /api/admin/trips/{trip}/publish returns 403 for non-admin', function () {
    $member = tripNonAdminUser();
    $doctor = Doctor::factory()->approved()->create();
    $trip   = Trip::factory()->create([
        'status'         => TripStatus::DRAFT->value,
        'lead_doctor_id' => $doctor->id,
    ]);

    $this->actingAs($member)
        ->postJson("/api/admin/trips/{$trip->id}/publish")
        ->assertForbidden();
});

it('POST /api/admin/trips/{trip}/publish returns 422 when trip is not DRAFT', function () {
    $admin = tripAdminUser();
    $trip  = Trip::factory()->published()->create();

    $this->actingAs($admin)
        ->postJson("/api/admin/trips/{$trip->id}/publish")
        ->assertStatus(422);
});

// ── POST /api/admin/trips/{trip}/close ───────────────────────────────────────

it('POST /api/admin/trips/{trip}/close transitions PUBLISHED trip to CLOSED', function () {
    $admin = tripAdminUser();
    $trip  = Trip::factory()->published()->create();

    $this->actingAs($admin)
        ->postJson("/api/admin/trips/{$trip->id}/close")
        ->assertOk()
        ->assertJsonPath('status', TripStatus::CLOSED->value);
});

it('POST /api/admin/trips/{trip}/close returns 403 for non-admin', function () {
    $member = tripNonAdminUser();
    $trip   = Trip::factory()->published()->create();

    $this->actingAs($member)
        ->postJson("/api/admin/trips/{$trip->id}/close")
        ->assertForbidden();
});

// ── POST /api/admin/trips/{trip}/cancel ──────────────────────────────────────

it('POST /api/admin/trips/{trip}/cancel cancels a trip and returns 200 with status CANCELLED', function () {
    $admin = tripAdminUser();
    $trip  = Trip::factory()->published()->create();

    $this->actingAs($admin)
        ->postJson("/api/admin/trips/{$trip->id}/cancel")
        ->assertOk()
        ->assertJsonPath('status', TripStatus::CANCELLED->value);
});

it('POST /api/admin/trips/{trip}/cancel returns 403 for non-admin', function () {
    $member = tripNonAdminUser();
    $trip   = Trip::factory()->published()->create();

    $this->actingAs($member)
        ->postJson("/api/admin/trips/{$trip->id}/cancel")
        ->assertForbidden();
});
