<?php

use App\Enums\UserRole;
use App\Enums\UserStatus;
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

function adminUser(): User
{
    $user = User::factory()->create();
    UserProfile::create(['user_id' => $user->id, 'first_name' => 'Admin', 'last_name' => 'User']);
    $user->addRole(UserRole::ADMIN);
    return $user->fresh();
}

function plainMemberUser(): User
{
    $user = User::factory()->create();
    UserProfile::create(['user_id' => $user->id, 'first_name' => 'Plain', 'last_name' => 'Member']);
    $user->addRole(UserRole::MEMBER);
    return $user->fresh();
}

// ── POST /api/admin/users/{user}/transition ───────────────────────────────────

it('POST /api/admin/users/{user}/transition admin transitions PENDING user to ACTIVE', function () {
    $admin  = adminUser();
    $target = User::factory()->pending()->create();

    $this->actingAs($admin)
        ->postJson("/api/admin/users/{$target->id}/transition", [
            'status' => UserStatus::ACTIVE->value,
        ])
        ->assertOk()
        ->assertJsonPath('status', UserStatus::ACTIVE->value);
});

it('POST /api/admin/users/{user}/transition returns 403 for non-admin', function () {
    $member = plainMemberUser();
    $target = User::factory()->pending()->create();

    $this->actingAs($member)
        ->postJson("/api/admin/users/{$target->id}/transition", [
            'status' => UserStatus::ACTIVE->value,
        ])
        ->assertForbidden();
});

it('POST /api/admin/users/{user}/transition returns 422 on invalid status value', function () {
    $admin  = adminUser();
    $target = User::factory()->create();

    $this->actingAs($admin)
        ->postJson("/api/admin/users/{$target->id}/transition", [
            'status' => 'NOT_A_REAL_STATUS',
        ])
        ->assertStatus(422);
});

it('POST /api/admin/users/{user}/transition returns 422 on invalid transition (ACTIVE to PENDING)', function () {
    // ACTIVE → PENDING is not an allowed transition; only SUSPENDED and DEACTIVATED are.
    $admin  = adminUser();
    $target = User::factory()->create(); // default status is ACTIVE

    $this->actingAs($admin)
        ->postJson("/api/admin/users/{$target->id}/transition", [
            'status' => UserStatus::PENDING->value,
        ])
        ->assertStatus(422);
});

// ── POST /api/admin/users/{user}/unlock ───────────────────────────────────────

it('POST /api/admin/users/{user}/unlock resets failed_login_count and locked_until', function () {
    $admin  = adminUser();
    $target = User::factory()->create([
        'failed_login_count' => 5,
        'locked_until'       => now()->addHour(),
    ]);

    $this->actingAs($admin)
        ->postJson("/api/admin/users/{$target->id}/unlock")
        ->assertOk()
        ->assertJsonPath('failed_login_count', 0)
        ->assertJsonPath('locked_until', null);
});

it('POST /api/admin/users/{user}/unlock returns 403 for non-admin', function () {
    $member = plainMemberUser();
    $target = User::factory()->create(['failed_login_count' => 3]);

    $this->actingAs($member)
        ->postJson("/api/admin/users/{$target->id}/unlock")
        ->assertForbidden();
});

// ── PUT /api/admin/users/{user}/roles ─────────────────────────────────────────

it('PUT /api/admin/users/{user}/roles admin assigns roles to a user', function () {
    $admin  = adminUser();
    $target = User::factory()->create();

    $this->actingAs($admin)
        ->putJson("/api/admin/users/{$target->id}/roles", [
            'roles' => [UserRole::MEMBER->value, UserRole::FINANCE_SPECIALIST->value],
        ])
        ->assertOk();

    expect($target->fresh()->hasRole(UserRole::FINANCE_SPECIALIST))->toBeTrue();
});

it('PUT /api/admin/users/{user}/roles admin removes all roles by passing empty array', function () {
    $admin  = adminUser();
    $target = User::factory()->create();
    $target->addRole(UserRole::MEMBER);

    $this->actingAs($admin)
        ->putJson("/api/admin/users/{$target->id}/roles", [
            'roles' => [],
        ])
        ->assertOk();

    expect($target->fresh()->hasRole(UserRole::MEMBER))->toBeFalse();
});

it('PUT /api/admin/users/{user}/roles returns 403 for non-admin', function () {
    $member = plainMemberUser();
    $target = User::factory()->create();

    $this->actingAs($member)
        ->putJson("/api/admin/users/{$target->id}/roles", [
            'roles' => [UserRole::MEMBER->value],
        ])
        ->assertForbidden();
});

it('PUT /api/admin/users/{user}/roles returns 422 on invalid role value', function () {
    $admin  = adminUser();
    $target = User::factory()->create();

    $this->actingAs($admin)
        ->putJson("/api/admin/users/{$target->id}/roles", [
            'roles' => ['NOT_A_REAL_ROLE'],
        ])
        ->assertStatus(422);
});
