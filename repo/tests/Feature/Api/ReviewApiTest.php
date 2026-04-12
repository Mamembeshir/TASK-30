<?php

use App\Enums\ReviewStatus;
use App\Enums\UserRole;
use App\Models\Trip;
use App\Models\TripReview;
use App\Models\TripSignup;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

// All mutation requests must include a same-origin Origin header so that
// VerifyApiCsrfToken grants the JSON exemption (mirrors real browser behaviour).
beforeEach(function () {
    $this->withHeaders(['Origin' => config('app.url')]);
});

// ── Helpers ───────────────────────────────────────────────────────────────────

function reviewMember(): User
{
    $user = User::factory()->create();
    UserProfile::create(['user_id' => $user->id, 'first_name' => 'Review', 'last_name' => 'Member']);
    $user->addRole(UserRole::MEMBER);
    return $user->fresh();
}

function reviewAdmin(): User
{
    $user = User::factory()->create();
    UserProfile::create(['user_id' => $user->id, 'first_name' => 'Admin', 'last_name' => 'User']);
    $user->addRole(UserRole::ADMIN);
    return $user->fresh();
}

// ── POST /api/trips/{trip}/reviews ────────────────────────────────────────────

it('POST /api/trips/{trip}/reviews allows eligible member with confirmed signup to create a review', function () {
    $user   = reviewMember();
    $trip   = Trip::factory()->published()->withSeats(5, 4)->create([
        'start_date' => now()->subDays(10),
        'end_date'   => now()->subDays(3),
    ]);
    $signup = TripSignup::factory()->confirmed()->create([
        'user_id' => $user->id,
        'trip_id' => $trip->id,
    ]);

    $this->actingAs($user)
        ->postJson("/api/trips/{$trip->id}/reviews", [
            'rating'          => 4,
            'review_text'     => 'Great trip, highly recommended!',
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertCreated()
        ->assertJsonPath('rating', 4);
});

it('POST /api/trips/{trip}/reviews is idempotent on same key', function () {
    $user   = reviewMember();
    $trip   = Trip::factory()->published()->withSeats(5, 4)->create([
        'start_date' => now()->subDays(10),
        'end_date'   => now()->subDays(3),
    ]);
    $signup = TripSignup::factory()->confirmed()->create([
        'user_id' => $user->id,
        'trip_id' => $trip->id,
    ]);
    $key = (string) Str::uuid();

    $r1 = $this->actingAs($user)
        ->postJson("/api/trips/{$trip->id}/reviews", [
            'rating'          => 5,
            'review_text'     => 'Amazing experience overall!',
            'idempotency_key' => $key,
        ])
        ->assertCreated();

    $r2 = $this->actingAs($user)
        ->postJson("/api/trips/{$trip->id}/reviews", [
            'rating'          => 1, // different rating — must be ignored
            'review_text'     => 'Terrible experience.',
            'idempotency_key' => $key,
        ])
        ->assertCreated();

    expect($r1->json('id'))->toBe($r2->json('id'));
    expect($r2->json('rating'))->toBe(5); // original preserved
});

it('POST /api/trips/{trip}/reviews returns 422 when user has no confirmed signup', function () {
    $user = reviewMember();
    $trip = Trip::factory()->published()->withSeats(5, 4)->create();
    // No signup created — user is not eligible

    $this->actingAs($user)
        ->postJson("/api/trips/{$trip->id}/reviews", [
            'rating'      => 4,
            'review_text' => 'Attempting a review without a booking.',
        ])
        ->assertStatus(422);
});

it('POST /api/trips/{trip}/reviews returns 422 on invalid rating', function () {
    $user   = reviewMember();
    $trip   = Trip::factory()->published()->withSeats(5, 4)->create();
    $signup = TripSignup::factory()->confirmed()->create([
        'user_id' => $user->id,
        'trip_id' => $trip->id,
    ]);

    // Rating 0 is below minimum (1–5)
    $this->actingAs($user)
        ->postJson("/api/trips/{$trip->id}/reviews", [
            'rating' => 0,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('rating');

    // Rating 6 is above maximum (1–5)
    $this->actingAs($user)
        ->postJson("/api/trips/{$trip->id}/reviews", [
            'rating' => 6,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('rating');
});

// ── PUT /api/reviews/{review} ─────────────────────────────────────────────────

it('PUT /api/reviews/{review} allows author to update their own review', function () {
    $user   = reviewMember();
    $trip   = Trip::factory()->published()->withSeats(5, 4)->create();
    $review = TripReview::factory()->create([
        'user_id'     => $user->id,
        'trip_id'     => $trip->id,
        'rating'      => 3,
        'review_text' => 'Original review text.',
    ]);

    $this->actingAs($user)
        ->putJson("/api/reviews/{$review->id}", [
            'rating'      => 5,
            'review_text' => 'Updated: much better after reflection.',
        ])
        ->assertOk()
        ->assertJsonPath('rating', 5);
});

it('PUT /api/reviews/{review} returns 403 when caller is not the author', function () {
    $author = reviewMember();
    $other  = reviewMember();
    $trip   = Trip::factory()->published()->withSeats(5, 4)->create();
    $review = TripReview::factory()->create([
        'user_id' => $author->id,
        'trip_id' => $trip->id,
    ]);

    $this->actingAs($other)
        ->putJson("/api/reviews/{$review->id}", [
            'rating' => 1,
        ])
        ->assertForbidden();
});

// ── POST /api/admin/reviews/{review}/flag ─────────────────────────────────────

it('POST /api/admin/reviews/{review}/flag allows admin to flag an ACTIVE review', function () {
    $admin  = reviewAdmin();
    $trip   = Trip::factory()->published()->withSeats(5, 4)->create();
    $review = TripReview::factory()->create([
        'trip_id' => $trip->id,
        'status'  => ReviewStatus::ACTIVE->value,
    ]);

    $this->actingAs($admin)
        ->postJson("/api/admin/reviews/{$review->id}/flag")
        ->assertOk()
        ->assertJsonPath('status', ReviewStatus::FLAGGED->value);
});

it('POST /api/admin/reviews/{review}/flag returns 403 for non-admin', function () {
    $member = reviewMember();
    $trip   = Trip::factory()->published()->withSeats(5, 4)->create();
    $review = TripReview::factory()->create(['trip_id' => $trip->id]);

    $this->actingAs($member)
        ->postJson("/api/admin/reviews/{$review->id}/flag")
        ->assertForbidden();
});

// ── POST /api/admin/reviews/{review}/remove ───────────────────────────────────

it('POST /api/admin/reviews/{review}/remove allows admin to remove an ACTIVE review', function () {
    $admin  = reviewAdmin();
    $trip   = Trip::factory()->published()->withSeats(5, 4)->create();
    $review = TripReview::factory()->create([
        'trip_id' => $trip->id,
        'status'  => ReviewStatus::ACTIVE->value,
    ]);

    $this->actingAs($admin)
        ->postJson("/api/admin/reviews/{$review->id}/remove")
        ->assertOk()
        ->assertJsonPath('status', ReviewStatus::REMOVED->value);
});

it('POST /api/admin/reviews/{review}/remove returns 403 for non-admin', function () {
    $member = reviewMember();
    $trip   = Trip::factory()->published()->withSeats(5, 4)->create();
    $review = TripReview::factory()->create(['trip_id' => $trip->id]);

    $this->actingAs($member)
        ->postJson("/api/admin/reviews/{$review->id}/remove")
        ->assertForbidden();
});

// ── POST /api/admin/reviews/{review}/restore ──────────────────────────────────

it('POST /api/admin/reviews/{review}/restore allows admin to restore a FLAGGED review to ACTIVE', function () {
    $admin  = reviewAdmin();
    $trip   = Trip::factory()->published()->withSeats(5, 4)->create();
    $review = TripReview::factory()->flagged()->create(['trip_id' => $trip->id]);

    $this->actingAs($admin)
        ->postJson("/api/admin/reviews/{$review->id}/restore")
        ->assertOk()
        ->assertJsonPath('status', ReviewStatus::ACTIVE->value);
});

it('POST /api/admin/reviews/{review}/restore returns 422 when review is not FLAGGED', function () {
    $admin  = reviewAdmin();
    $trip   = Trip::factory()->published()->withSeats(5, 4)->create();
    $review = TripReview::factory()->create([
        'trip_id' => $trip->id,
        'status'  => ReviewStatus::ACTIVE->value, // ACTIVE, not FLAGGED
    ]);

    $this->actingAs($admin)
        ->postJson("/api/admin/reviews/{$review->id}/restore")
        ->assertStatus(422);
});

it('POST /api/admin/reviews/{review}/restore returns 403 for non-admin', function () {
    $member = reviewMember();
    $trip   = Trip::factory()->published()->withSeats(5, 4)->create();
    $review = TripReview::factory()->flagged()->create(['trip_id' => $trip->id]);

    $this->actingAs($member)
        ->postJson("/api/admin/reviews/{$review->id}/restore")
        ->assertForbidden();
});
