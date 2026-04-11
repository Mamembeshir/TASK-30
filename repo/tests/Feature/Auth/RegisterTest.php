<?php

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ── Happy path ────────────────────────────────────────────────────────────────

it('registers a new user with ACTIVE status and Member role', function () {
    Livewire::test(\App\Livewire\Auth\Register::class)
        ->set('username', 'johndoe')
        ->set('email', 'john@example.com')
        ->set('first_name', 'John')
        ->set('last_name', 'Doe')
        ->set('password', 'Secure!Pass1')
        ->set('password_confirmation', 'Secure!Pass1')
        ->call('register')
        ->assertRedirect(route('dashboard'));

    $user = User::where('email', 'john@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user->status)->toBe(UserStatus::ACTIVE);
    expect($user->hasRole(UserRole::MEMBER))->toBeTrue();
    $this->assertAuthenticatedAs($user);
});

it('creates a user profile with first and last name', function () {
    Livewire::test(\App\Livewire\Auth\Register::class)
        ->set('username', 'janedoe')
        ->set('email', 'jane@example.com')
        ->set('first_name', 'Jane')
        ->set('last_name', 'Doe')
        ->set('password', 'Secure!Pass1')
        ->set('password_confirmation', 'Secure!Pass1')
        ->call('register');

    $user = User::where('email', 'jane@example.com')->first();

    expect($user->profile)->not->toBeNull();
    expect($user->profile->first_name)->toBe('Jane');
    expect($user->profile->last_name)->toBe('Doe');
});

// ── Duplicate checks ──────────────────────────────────────────────────────────

it('rejects duplicate username with 422', function () {
    User::factory()->create(['username' => 'taken']);

    Livewire::test(\App\Livewire\Auth\Register::class)
        ->set('username', 'taken')
        ->set('email', 'new@example.com')
        ->set('first_name', 'New')
        ->set('last_name', 'User')
        ->set('password', 'Secure!Pass1')
        ->set('password_confirmation', 'Secure!Pass1')
        ->call('register')
        ->assertHasErrors('username');
});

it('rejects duplicate email', function () {
    User::factory()->create(['email' => 'exists@example.com']);

    Livewire::test(\App\Livewire\Auth\Register::class)
        ->set('username', 'newuser')
        ->set('email', 'exists@example.com')
        ->set('first_name', 'New')
        ->set('last_name', 'User')
        ->set('password', 'Secure!Pass1')
        ->set('password_confirmation', 'Secure!Pass1')
        ->call('register')
        ->assertHasErrors('email');
});

// ── Password policy (AUTH-01) ─────────────────────────────────────────────────

it('rejects password shorter than 10 characters', function () {
    Livewire::test(\App\Livewire\Auth\Register::class)
        ->set('username', 'user1')
        ->set('email', 'u1@example.com')
        ->set('first_name', 'A')
        ->set('last_name', 'B')
        ->set('password', 'Short1!')
        ->set('password_confirmation', 'Short1!')
        ->call('register')
        ->assertHasErrors('password');
});

it('rejects password without uppercase letter', function () {
    Livewire::test(\App\Livewire\Auth\Register::class)
        ->set('username', 'user2')
        ->set('email', 'u2@example.com')
        ->set('first_name', 'A')
        ->set('last_name', 'B')
        ->set('password', 'nouppercase1!')
        ->set('password_confirmation', 'nouppercase1!')
        ->call('register')
        ->assertHasErrors('password');
});

it('rejects password without digit', function () {
    Livewire::test(\App\Livewire\Auth\Register::class)
        ->set('username', 'user3')
        ->set('email', 'u3@example.com')
        ->set('first_name', 'A')
        ->set('last_name', 'B')
        ->set('password', 'NoDigitHere!')
        ->set('password_confirmation', 'NoDigitHere!')
        ->call('register')
        ->assertHasErrors('password');
});

it('rejects password without special character', function () {
    Livewire::test(\App\Livewire\Auth\Register::class)
        ->set('username', 'user4')
        ->set('email', 'u4@example.com')
        ->set('first_name', 'A')
        ->set('last_name', 'B')
        ->set('password', 'NoSpecialChar1')
        ->set('password_confirmation', 'NoSpecialChar1')
        ->call('register')
        ->assertHasErrors('password');
});

it('rejects mismatched password confirmation', function () {
    Livewire::test(\App\Livewire\Auth\Register::class)
        ->set('username', 'user5')
        ->set('email', 'u5@example.com')
        ->set('first_name', 'A')
        ->set('last_name', 'B')
        ->set('password', 'Secure!Pass1')
        ->set('password_confirmation', 'DifferentPass1!')
        ->call('register')
        ->assertHasErrors('password');
});

// ── Offline constraint regression (Audit Issue 1) ─────────────────────────────
//
// The audit flagged `Password::uncompromised()` because it calls the
// HaveIBeenPwned range API over the internet, which violates the
// "no network at runtime" constraint documented in docs/claude.md. This test
// pins the fix: registration with a strong password must succeed without any
// outbound HTTP call. `Http::preventStrayRequests()` makes the test fail loudly
// if anything inside the register flow tries to reach the network.

it('completes registration with zero outbound HTTP calls (offline regression)', function () {
    Http::preventStrayRequests();
    Http::fake(); // empty fake — any actual call would now trigger the prevention guard

    Livewire::test(\App\Livewire\Auth\Register::class)
        ->set('username', 'offlineuser')
        ->set('email', 'offline@example.com')
        ->set('first_name', 'Offline')
        ->set('last_name', 'User')
        ->set('password', 'Secure!Pass1')
        ->set('password_confirmation', 'Secure!Pass1')
        ->call('register')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    expect(User::where('email', 'offline@example.com')->exists())->toBeTrue();
});
