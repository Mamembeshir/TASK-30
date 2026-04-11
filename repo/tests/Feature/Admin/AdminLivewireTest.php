<?php

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Livewire\Admin\AuditLogViewer;
use App\Livewire\Admin\SystemConfig;
use App\Livewire\Admin\UserList;
use App\Livewire\Admin\UserManagement;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function adminUser(): User
{
    $user = User::factory()->create(['status' => UserStatus::ACTIVE]);
    UserProfile::create(['user_id' => $user->id, 'first_name' => 'Admin', 'last_name' => 'User']);
    $user->addRole(UserRole::ADMIN);
    return $user->fresh();
}

function memberOnly(): User
{
    $user = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $user->addRole(UserRole::MEMBER);
    return $user->fresh();
}

// ── AuditLogViewer ─────────────────────────────────────────────────────────────

it('AuditLogViewer renders for admin', function () {
    Livewire::actingAs(adminUser())
        ->test(AuditLogViewer::class)
        ->assertOk();
});

it('AuditLogViewer route is forbidden for non-admin', function () {
    $this->actingAs(memberOnly())
        ->get(route('admin.audit'))
        ->assertForbidden();
});

// ── SystemConfig ───────────────────────────────────────────────────────────────

it('SystemConfig renders for admin', function () {
    Livewire::actingAs(adminUser())
        ->test(SystemConfig::class)
        ->assertOk();
});

it('SystemConfig route is forbidden for non-admin', function () {
    $this->actingAs(memberOnly())
        ->get(route('admin.config'))
        ->assertForbidden();
});

// ── UserList ───────────────────────────────────────────────────────────────────

it('UserList renders for admin', function () {
    Livewire::actingAs(adminUser())
        ->test(UserList::class)
        ->assertOk();
});

it('UserList search property updates without error', function () {
    Livewire::actingAs(adminUser())
        ->test(UserList::class)
        ->set('search', 'somequery')
        ->assertOk()
        ->set('filterStatus', 'ACTIVE')
        ->assertOk();
});

it('UserList route is forbidden for non-admin', function () {
    $this->actingAs(memberOnly())
        ->get(route('admin.users'))
        ->assertForbidden();
});

// ── UserManagement ─────────────────────────────────────────────────────────────

it('UserManagement renders for admin', function () {
    Livewire::actingAs(adminUser())
        ->test(UserManagement::class)
        ->assertOk();
});
