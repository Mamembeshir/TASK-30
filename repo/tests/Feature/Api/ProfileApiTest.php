<?php

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// All mutation requests must include a same-origin Origin header so that
// VerifyApiCsrfToken grants the JSON exemption (mirrors real browser behaviour).
beforeEach(function () {
    $this->withHeaders(['Origin' => config('app.url')]);
});

// ── PUT /api/profile ──────────────────────────────────────────────────────────

it('PUT /api/profile saves basic profile and returns 200 with correct names', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->putJson('/api/profile', [
            'first_name' => 'Jane',
            'last_name'  => 'Doe',
        ])
        ->assertOk()
        ->assertJsonPath('first_name', 'Jane')
        ->assertJsonPath('last_name', 'Doe');
});

it('PUT /api/profile saves optional fields phone and date_of_birth and returns 200', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->putJson('/api/profile', [
            'first_name'    => 'Jane',
            'last_name'     => 'Doe',
            'phone'         => '555-867-5309',
            'date_of_birth' => '1990-06-15',
        ])
        ->assertOk()
        ->assertJsonPath('first_name', 'Jane')
        ->assertJsonPath('phone', '555-867-5309');
});

it('PUT /api/profile saves address (encrypted at rest) and returns 200', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->putJson('/api/profile', [
            'first_name' => 'Jane',
            'last_name'  => 'Doe',
            'address'    => '123 Main Street, Springfield, IL 62701',
        ])
        ->assertOk();
});

it('PUT /api/profile returns 422 on missing first_name', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->putJson('/api/profile', [
            'last_name' => 'Doe',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('first_name');
});

it('PUT /api/profile returns 422 on future date_of_birth', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->putJson('/api/profile', [
            'first_name'    => 'Jane',
            'last_name'     => 'Doe',
            'date_of_birth' => now()->addYear()->toDateString(),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('date_of_birth');
});

it('PUT /api/profile returns 422 on invalid ssn_fragment (non-digit or not 4 chars)', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->putJson('/api/profile', [
            'first_name'   => 'Jane',
            'last_name'    => 'Doe',
            'ssn_fragment' => 'abc',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('ssn_fragment');
});

it('PUT /api/profile returns 401 when unauthenticated', function () {
    $this->putJson('/api/profile', [
        'first_name' => 'Jane',
        'last_name'  => 'Doe',
    ])->assertUnauthorized();
});
