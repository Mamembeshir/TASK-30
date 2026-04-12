<?php

/**
 * CSRF behaviour of /api/* mutation routes.
 *
 * VerifyApiCsrfToken implements a three-tier model:
 *   1. JSON requests with a same-origin Origin header are exempt — cross-origin
 *      form submissions cannot set application/json without a CORS preflight,
 *      and we additionally verify Origin matches app.url.
 *   2. JSON requests with no Origin, or a cross-origin Origin, fall through to
 *      the synchronizer-token check.
 *   3. Non-JSON mutation requests (form POST / URL-encoded) must always supply
 *      a synchronizer token (_token body field or X-CSRF-TOKEN header).
 *
 * These tests pin all three tiers so regressions are caught immediately.
 */

use App\Enums\UserRole;
use App\Models\Trip;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── helpers ───────────────────────────────────────────────────────────────────

function csrfMember(): User
{
    $user = User::factory()->create();
    UserProfile::create(['user_id' => $user->id, 'first_name' => 'Csrf', 'last_name' => 'Tester']);
    $user->addRole(UserRole::MEMBER);
    return $user->fresh();
}

function csrfPublishedTrip(): Trip
{
    $doctor = \App\Models\Doctor::factory()->approved()->create();
    return Trip::factory()->published()->withSeats(5, 5)->create([
        'lead_doctor_id' => $doctor->id,
    ]);
}

// ── non-JSON POST (form submission vector) ────────────────────────────────────

it('non-JSON POST without CSRF token is rejected with 419', function () {
    $member = csrfMember();
    $trip   = csrfPublishedTrip();

    // Simulates a cross-site form POST: no JSON Content-Type, no CSRF token.
    // VerifyApiCsrfToken must reject this before the route handler runs.
    $this->actingAs($member)
        ->post("/api/trips/{$trip->id}/hold")
        ->assertStatus(419);
});

it('non-JSON POST with mismatched CSRF token is rejected with 419', function () {
    $member = csrfMember();
    $trip   = csrfPublishedTrip();

    // Session token is 'session-token'; request sends 'wrong-token'.
    $this->actingAs($member)
        ->withSession(['_token' => 'session-token'])
        ->post("/api/trips/{$trip->id}/hold", ['_token' => 'wrong-token'])
        ->assertStatus(419);
});

it('non-JSON POST with matching CSRF token passes the CSRF gate', function () {
    $member = csrfMember();
    $trip   = csrfPublishedTrip();

    // Session token matches request token → CSRF passes; route handler runs.
    // The hold succeeds (returns 201) proving execution reached the controller.
    $this->actingAs($member)
        ->withSession(['_token' => 'test-csrf-token'])
        ->post("/api/trips/{$trip->id}/hold", ['_token' => 'test-csrf-token'])
        ->assertStatus(201);
});

// ── JSON POST (normal API usage) ──────────────────────────────────────────────

it('JSON POST with same-origin Origin passes CSRF gate without any token', function () {
    $member = csrfMember();
    $trip   = csrfPublishedTrip();

    // postJson sets Content-Type: application/json; same-origin Origin header
    // proves browser cooperation — isJsonRequest() returns true and the
    // synchronizer-token check is skipped.
    $this->actingAs($member)
        ->withHeaders(['Origin' => config('app.url')])
        ->postJson("/api/trips/{$trip->id}/hold")
        ->assertStatus(201);
});

// ── Cross-origin Origin header hardening ──────────────────────────────────────

it('cross-origin JSON POST without CSRF token is rejected with 419', function () {
    $member = csrfMember();
    $trip   = csrfPublishedTrip();

    // Simulates a JSON XHR from a hostile origin (e.g. https://evil.example.com).
    // VerifyApiCsrfToken detects Origin ≠ app.url and falls through to the
    // synchronizer-token check, which fails → 419.
    $this->actingAs($member)
        ->withHeaders(['Origin' => 'https://evil.example.com'])
        ->postJson("/api/trips/{$trip->id}/hold")
        ->assertStatus(419);
});

it('same-origin JSON POST passes CSRF gate without a token', function () {
    $member = csrfMember();
    $trip   = csrfPublishedTrip();

    // A legitimate browser XHR from the same origin — still no token needed.
    $this->actingAs($member)
        ->withHeaders(['Origin' => config('app.url')])
        ->postJson("/api/trips/{$trip->id}/hold")
        ->assertStatus(201);
});

it('JSON POST without Origin header is rejected with 419', function () {
    $member = csrfMember();
    $trip   = csrfPublishedTrip();

    // No Origin header → VerifyApiCsrfToken cannot confirm a browser same-origin
    // context, so the JSON exemption does NOT apply.  The request falls through
    // to the synchronizer-token check, which fails → 419.
    // (In-process Livewire → controller calls never reach this middleware at all.)
    $this->actingAs($member)
        ->postJson("/api/trips/{$trip->id}/hold")
        ->assertStatus(419);
});

it('JSON POST without Origin header but with matching CSRF token passes', function () {
    $member = csrfMember();
    $trip   = csrfPublishedTrip();

    // Non-browser callers (CLI, server-to-server) can still reach the API by
    // supplying a valid synchronizer token obtained from the session.
    $this->actingAs($member)
        ->withSession(['_token' => 'test-csrf-token'])
        ->withHeaders(['X-CSRF-TOKEN' => 'test-csrf-token'])
        ->postJson("/api/trips/{$trip->id}/hold")
        ->assertStatus(201);
});
