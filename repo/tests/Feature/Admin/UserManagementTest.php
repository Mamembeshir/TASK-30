<?php

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\StaleRecordException;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ── Helper ────────────────────────────────────────────────────────────────────

function makeAdmin(): User
{
    $admin = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $admin->roles()->create(['role' => UserRole::ADMIN->value, 'assigned_at' => now()]);
    return $admin;
}

function makeMember(): User
{
    $member = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $member->roles()->create(['role' => UserRole::MEMBER->value, 'assigned_at' => now()]);
    return $member;
}

// ── Access control ────────────────────────────────────────────────────────────

it('allows admin to access user list', function () {
    $admin = makeAdmin();

    $this->actingAs($admin)
         ->get(route('admin.users'))
         ->assertOk();
});

it('denies member access to user list with 403', function () {
    $member = makeMember();

    $this->actingAs($member)
         ->get(route('admin.users'))
         ->assertForbidden();
});

it('denies unauthenticated access to admin routes', function () {
    $this->get(route('admin.users'))->assertRedirect(route('login'));
});

// ── Status transitions ────────────────────────────────────────────────────────

it('admin can transition ACTIVE user to SUSPENDED', function () {
    $admin  = makeAdmin();
    $target = User::factory()->create(['status' => UserStatus::ACTIVE]);

    $this->actingAs($admin);

    Livewire::test(\App\Livewire\Admin\UserDetail::class, ['user' => $target])
        ->call('transitionTo', UserStatus::SUSPENDED->value)
        ->assertHasNoErrors();

    expect($target->fresh()->status)->toBe(UserStatus::SUSPENDED);
});

it('admin can reactivate a SUSPENDED user', function () {
    $admin  = makeAdmin();
    $target = User::factory()->create(['status' => UserStatus::SUSPENDED]);

    $this->actingAs($admin);

    Livewire::test(\App\Livewire\Admin\UserDetail::class, ['user' => $target])
        ->call('transitionTo', UserStatus::ACTIVE->value)
        ->assertHasNoErrors();

    expect($target->fresh()->status)->toBe(UserStatus::ACTIVE);
});

it('rejects invalid transition from DEACTIVATED', function () {
    $admin  = makeAdmin();
    $target = User::factory()->create(['status' => UserStatus::DEACTIVATED]);

    $this->actingAs($admin);

    Livewire::test(\App\Livewire\Admin\UserDetail::class, ['user' => $target])
        ->call('transitionTo', UserStatus::ACTIVE->value)
        ->assertHasErrors('status');

    expect($target->fresh()->status)->toBe(UserStatus::DEACTIVATED);
});

// ── Role management ───────────────────────────────────────────────────────────

it('admin can add Doctor role to a Member', function () {
    $admin  = makeAdmin();
    $member = makeMember();

    $this->actingAs($admin);

    Livewire::test(\App\Livewire\Admin\UserDetail::class, ['user' => $member])
        ->set('selectedRoles', [
            UserRole::MEMBER->value => true,
            UserRole::DOCTOR->value => true,
        ])
        ->call('saveRoles')
        ->assertHasNoErrors();

    expect($member->fresh()->hasRole(UserRole::DOCTOR))->toBeTrue();
    expect($member->fresh()->hasRole(UserRole::MEMBER))->toBeTrue();
});

it('user with multiple roles gets union of access', function () {
    $user = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $user->roles()->createMany([
        ['role' => UserRole::MEMBER->value, 'assigned_at' => now()],
        ['role' => UserRole::DOCTOR->value, 'assigned_at' => now()],
    ]);

    expect($user->hasRole(UserRole::MEMBER))->toBeTrue();
    expect($user->hasRole(UserRole::DOCTOR))->toBeTrue();
    expect($user->hasRole(UserRole::ADMIN))->toBeFalse();
});

// ── Optimistic locking ────────────────────────────────────────────────────────

it('user model PUT with stale version throws StaleRecordException (HTTP 409)', function () {
    $user = User::factory()->create(['version' => 1]);

    // Simulate concurrent update
    User::where('id', $user->id)->update(['version' => 5]);

    $user->username = 'new_name';

    expect(fn () => $user->saveWithLock())
        ->toThrow(StaleRecordException::class);

    // The exception code maps to HTTP 409
    try {
        $user->saveWithLock();
    } catch (StaleRecordException $e) {
        expect($e->getCode())->toBe(409);
    }
});
