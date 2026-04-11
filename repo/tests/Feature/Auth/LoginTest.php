<?php

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('shows the login page', function () {
    $this->get(route('login'))->assertOk();
});

it('redirects authenticated users away from login', function () {
    $user = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $this->actingAs($user)
         ->get(route('login'))
         ->assertRedirect(route('dashboard'));
});

// ── Happy path ────────────────────────────────────────────────────────────────

it('logs in with valid credentials and creates a session', function () {
    $user = User::factory()->create([
        'password' => bcrypt('Password1!abc'),
        'status'   => UserStatus::ACTIVE,
    ]);

    Livewire::test(\App\Livewire\Auth\Login::class)
        ->set('login', $user->email)
        ->set('password', 'Password1!abc')
        ->call('login')
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('resets failed_login_count on successful login', function () {
    $user = User::factory()->create([
        'password'           => bcrypt('Password1!abc'),
        'status'             => UserStatus::ACTIVE,
        'failed_login_count' => 3,
    ]);

    Livewire::test(\App\Livewire\Auth\Login::class)
        ->set('login', $user->email)
        ->set('password', 'Password1!abc')
        ->call('login');

    expect($user->fresh()->failed_login_count)->toBe(0);
});

// ── Wrong password ─────────────────────────────────────────────────────────

it('increments failed_login_count on wrong password', function () {
    $user = User::factory()->create([
        'password' => bcrypt('Password1!abc'),
        'status'   => UserStatus::ACTIVE,
    ]);

    Livewire::test(\App\Livewire\Auth\Login::class)
        ->set('login', $user->email)
        ->set('password', 'wrongpassword')
        ->call('login')
        ->assertHasErrors('login');

    expect($user->fresh()->failed_login_count)->toBe(1);
    $this->assertGuest();
});

// ── Lockout (AUTH-02) ─────────────────────────────────────────────────────────

it('locks account after 5 failed attempts', function () {
    $user = User::factory()->create([
        'password' => bcrypt('Password1!abc'),
        'status'   => UserStatus::ACTIVE,
    ]);

    $component = Livewire::test(\App\Livewire\Auth\Login::class);

    foreach (range(1, 5) as $i) {
        $component->set('login', $user->email)
                  ->set('password', 'wrongpassword')
                  ->call('login');
    }

    expect($user->fresh()->locked_until)->not->toBeNull();
    expect($user->fresh()->isLocked())->toBeTrue();
});

it('returns locked error on 6th attempt and does not increment count', function () {
    $user = User::factory()->create([
        'password'           => bcrypt('Password1!abc'),
        'status'             => UserStatus::ACTIVE,
        'failed_login_count' => 5,
        'locked_until'       => now()->addMinutes(15),
    ]);

    Livewire::test(\App\Livewire\Auth\Login::class)
        ->set('login', $user->email)
        ->set('password', 'wrongpassword')
        ->call('login')
        ->assertHasErrors('login');

    // Count must NOT have incremented (AUTH-02: count doesn't increment during lockout)
    expect($user->fresh()->failed_login_count)->toBe(5);
});

it('does not extend the lock timer during lockout period', function () {
    $lockedUntil = now()->addMinutes(10);

    $user = User::factory()->create([
        'password'           => bcrypt('Password1!abc'),
        'status'             => UserStatus::ACTIVE,
        'failed_login_count' => 5,
        'locked_until'       => $lockedUntil,
    ]);

    Livewire::test(\App\Livewire\Auth\Login::class)
        ->set('login', $user->email)
        ->set('password', 'wrongpassword')
        ->call('login');

    // locked_until should be unchanged (compare unix seconds; DB truncates microseconds)
    expect($user->fresh()->locked_until->timestamp)
        ->toBe($lockedUntil->timestamp);
});

// ── Status checks (AUTH-05) ───────────────────────────────────────────────────

it('rejects login while SUSPENDED', function () {
    $user = User::factory()->create([
        'password' => bcrypt('Password1!abc'),
        'status'   => UserStatus::SUSPENDED,
    ]);

    Livewire::test(\App\Livewire\Auth\Login::class)
        ->set('login', $user->email)
        ->set('password', 'Password1!abc')
        ->call('login')
        ->assertHasErrors('login');

    $this->assertGuest();
});

it('blocks access to dashboard when account becomes suspended mid-session', function () {
    $user = User::factory()->create([
        'password' => bcrypt('Password1!abc'),
        'status'   => UserStatus::SUSPENDED,
    ]);

    $this->actingAs($user)
         ->get(route('dashboard'))
         ->assertRedirect(route('login'));
});
