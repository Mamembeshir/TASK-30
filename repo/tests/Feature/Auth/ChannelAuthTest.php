<?php

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Private channel authorization (channels.php:15) ──────────────────────────
//
// The user.{userId} channel is used for personal notifications (waitlist
// offers, hold-expiry warnings). Its callback must allow the *owning* user
// and reject every other authenticated user.

it('user private channel authorizes the matching user', function () {
    $owner = User::factory()->create(['status' => UserStatus::ACTIVE]);

    $this->actingAs($owner)
        ->postJson('/broadcasting/auth', [
            'socket_id'    => '123.456',
            'channel_name' => 'private-user.' . $owner->id,
        ])
        ->assertSuccessful();
});

it('user private channel denies a different authenticated user', function () {
    $owner = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $other = User::factory()->create(['status' => UserStatus::ACTIVE]);

    // $other is authenticated but is trying to subscribe to $owner's channel.
    $this->actingAs($other)
        ->postJson('/broadcasting/auth', [
            'socket_id'    => '123.456',
            'channel_name' => 'private-user.' . $owner->id,
        ])
        ->assertStatus(403);
});

it('user private channel denies an unauthenticated request', function () {
    $owner = User::factory()->create(['status' => UserStatus::ACTIVE]);

    // No actingAs — guest request must be rejected.
    $this->postJson('/broadcasting/auth', [
        'socket_id'    => '123.456',
        'channel_name' => 'private-user.' . $owner->id,
    ])
    ->assertStatus(403);
});
