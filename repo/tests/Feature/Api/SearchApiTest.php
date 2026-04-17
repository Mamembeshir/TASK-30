<?php

use App\Enums\UserRole;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\UserSearchHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

// All mutation requests must include a same-origin Origin header so that
// VerifyApiCsrfToken grants the JSON exemption (mirrors real browser behaviour).
beforeEach(function () {
    $this->withHeaders(['Origin' => config('app.url')]);
});

// ── Helpers ───────────────────────────────────────────────────────────────────

function searchApiMember(): User
{
    $user = User::factory()->create();
    UserProfile::create(['user_id' => $user->id, 'first_name' => 'Search', 'last_name' => 'User']);
    $user->addRole(UserRole::MEMBER);
    return $user->fresh();
}

// ── POST /api/search/history/clear ────────────────────────────────────────────

it('POST /api/search/history/clear clears history and returns confirmation message', function () {
    $user = searchApiMember();
    UserSearchHistory::factory()->count(3)->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->postJson('/api/search/history/clear')
        ->assertOk()
        ->assertJsonPath('message', 'Search history cleared.');

    expect(UserSearchHistory::where('user_id', $user->id)->count())->toBe(0);
});

it('POST /api/search/history/clear succeeds when history is already empty', function () {
    $user = searchApiMember();

    $this->actingAs($user)
        ->postJson('/api/search/history/clear')
        ->assertOk()
        ->assertJsonPath('message', 'Search history cleared.');
});

it('POST /api/search/history/clear is idempotent on the same key', function () {
    $user = searchApiMember();
    UserSearchHistory::factory()->count(2)->create(['user_id' => $user->id]);
    $key  = (string) Str::uuid();

    $r1 = $this->actingAs($user)
        ->postJson('/api/search/history/clear', ['idempotency_key' => $key])
        ->assertOk()
        ->assertJsonPath('message', 'Search history cleared.');

    // Second call with the same key — history already gone, should still return 200
    $r2 = $this->actingAs($user)
        ->postJson('/api/search/history/clear', ['idempotency_key' => $key])
        ->assertOk()
        ->assertJsonPath('message', 'Search history cleared.');

    // Idempotency-Key header variant should also work
    $this->actingAs($user)
        ->withHeaders(['Idempotency-Key' => $key])
        ->postJson('/api/search/history/clear')
        ->assertOk();
});

it('POST /api/search/history/clear only clears the authenticated user\'s own history', function () {
    $userA = searchApiMember();
    $userB = searchApiMember();

    UserSearchHistory::factory()->count(3)->create(['user_id' => $userA->id]);
    UserSearchHistory::factory()->count(2)->create(['user_id' => $userB->id]);

    $this->actingAs($userA)
        ->postJson('/api/search/history/clear')
        ->assertOk();

    expect(UserSearchHistory::where('user_id', $userA->id)->count())->toBe(0)
        ->and(UserSearchHistory::where('user_id', $userB->id)->count())->toBe(2);
});

it('POST /api/search/history/clear returns 401 when unauthenticated', function () {
    $this->postJson('/api/search/history/clear')->assertUnauthorized();
});
