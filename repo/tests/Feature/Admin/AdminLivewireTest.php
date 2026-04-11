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

/**
 * Helper: pull the paginated `logs` collection out of a Livewire test instance.
 * The component renders both the table and the action/entity filter dropdowns
 * from the same page, so HTML scraping (assertSee/assertDontSee) is unreliable
 * for filter assertions — we assert against the underlying view data instead.
 */
function auditLogActions($component): array
{
    $logs = $component->viewData('logs');
    return collect($logs->items())->pluck('action')->all();
}

it('AuditLogViewer filters by actor (email/username/uuid)', function () {
    $admin  = adminUser();
    $member = memberOnly();

    \App\Models\AuditLog::record($admin->id,  'action.admin',  'X', null);
    \App\Models\AuditLog::record($member->id, 'action.member', 'X', null);

    $page = Livewire::actingAs($admin)->test(AuditLogViewer::class);

    $page->set('actorFilter', $member->email);
    expect(auditLogActions($page))->toBe(['action.member']);

    $page->set('actorFilter', $member->username);
    expect(auditLogActions($page))->toBe(['action.member']);

    $page->set('actorFilter', $member->id);
    expect(auditLogActions($page))->toBe(['action.member']);

    $page->set('actorFilter', 'no-such-user@example.com');
    expect(auditLogActions($page))->toBe([]);
});

it('AuditLogViewer filters by date range', function () {
    $admin = adminUser();

    // The audit log is append-only at both the model layer *and* the
    // PostgreSQL layer (BEFORE UPDATE/DELETE trigger — audit Issue 4), so
    // we cannot backdate an existing row. Instead we travel back in time
    // before writing the old entry so its created_at is genuinely old.
    \Illuminate\Support\Carbon::setTestNow(now()->subDays(10));
    \App\Models\AuditLog::record($admin->id, 'action.old', 'X', null);
    \Illuminate\Support\Carbon::setTestNow(); // back to real time

    \App\Models\AuditLog::record($admin->id, 'action.recent', 'X', null);

    $page = Livewire::actingAs($admin)
        ->test(AuditLogViewer::class)
        ->set('dateFromFilter', now()->subDays(1)->toDateString())
        ->set('dateToFilter',   now()->toDateString());

    expect(auditLogActions($page))->toContain('action.recent')
        ->and(auditLogActions($page))->not->toContain('action.old');
});

it('AuditLogViewer filters by correlation id', function () {
    $admin = adminUser();
    $cid   = (string) \Illuminate\Support\Str::uuid();

    \App\Models\AuditLog::record($admin->id, 'action.matched',   'X', null, null, null, null, null, $cid);
    \App\Models\AuditLog::record($admin->id, 'action.unrelated', 'X', null);

    $page = Livewire::actingAs($admin)
        ->test(AuditLogViewer::class)
        ->set('correlationIdFilter', $cid);

    expect(auditLogActions($page))->toBe(['action.matched']);
});

it('AuditLogViewer filters by entity type', function () {
    $admin = adminUser();

    \App\Models\AuditLog::record($admin->id, 'action.trip',    'Trip',    null);
    \App\Models\AuditLog::record($admin->id, 'action.payment', 'Payment', null);

    $page = Livewire::actingAs($admin)
        ->test(AuditLogViewer::class)
        ->set('entityFilter', 'Trip');

    expect(auditLogActions($page))->toBe(['action.trip']);
});

it('AuditLogViewer clearFilters resets every investigation filter', function () {
    Livewire::actingAs(adminUser())
        ->test(AuditLogViewer::class)
        ->set('search',              'foo')
        ->set('actionFilter',        'user.login')
        ->set('entityFilter',        'User')
        ->set('actorFilter',         'someone@example.com')
        ->set('dateFromFilter',      '2026-01-01')
        ->set('dateToFilter',        '2026-04-01')
        ->set('correlationIdFilter', 'abc-123')
        ->call('clearFilters')
        ->assertSet('search',              '')
        ->assertSet('actionFilter',        '')
        ->assertSet('entityFilter',        '')
        ->assertSet('actorFilter',         '')
        ->assertSet('dateFromFilter',      '')
        ->assertSet('dateToFilter',        '')
        ->assertSet('correlationIdFilter', '');
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
