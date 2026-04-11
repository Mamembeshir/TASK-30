<?php

use App\Enums\ReviewStatus;
use App\Enums\SignupStatus;
use App\Models\Trip;
use App\Models\TripReview;
use App\Models\TripSignup;
use App\Models\User;
use App\Services\ReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * Helper: each ReviewService::create call needs a unique idempotency key
 * to satisfy the contract introduced for audit Issue 3.
 */
function reviewKey(): string
{
    return (string) Str::uuid();
}

// ── REV-01: eligibility ───────────────────────────────────────────────────────

it('allows a confirmed member on a past trip to review', function () {
    $trip   = Trip::factory()->create(['end_date' => now()->subDay()]);
    $user   = User::factory()->create();
    TripSignup::factory()->for($trip)->for($user)->confirmed()->create();

    $review = app(ReviewService::class)->create($trip, $user, 4, 'Great trip!', reviewKey());

    expect($review->rating)->toBe(4)
        ->and($review->status)->toBe(ReviewStatus::ACTIVE);
});

it('rejects review when signup is HOLD (not CONFIRMED)', function () {
    $trip = Trip::factory()->create(['end_date' => now()->subDay()]);
    $user = User::factory()->create();
    TripSignup::factory()->for($trip)->for($user)->create(['status' => SignupStatus::HOLD->value]);

    expect(fn () => app(ReviewService::class)->create($trip, $user, 4, null, reviewKey()))
        ->toThrow(RuntimeException::class, 'confirmed signup');
});

it('rejects review when user has no signup', function () {
    $trip = Trip::factory()->create(['end_date' => now()->subDay()]);
    $user = User::factory()->create();

    expect(fn () => app(ReviewService::class)->create($trip, $user, 3, null, reviewKey()))
        ->toThrow(RuntimeException::class, 'confirmed signup');
});

it('rejects review when trip has not ended yet', function () {
    $trip = Trip::factory()->create([
        'start_date' => now()->addDays(5),
        'end_date'   => now()->addDays(12),
    ]);
    $user = User::factory()->create();
    TripSignup::factory()->for($trip)->for($user)->confirmed()->create();

    expect(fn () => app(ReviewService::class)->create($trip, $user, 5, null, reviewKey()))
        ->toThrow(RuntimeException::class, 'after it has ended');
});

// ── REV-02: one per user per trip ─────────────────────────────────────────────

it('rejects a second review for the same trip', function () {
    $trip = Trip::factory()->create(['end_date' => now()->subDay()]);
    $user = User::factory()->create();
    TripSignup::factory()->for($trip)->for($user)->confirmed()->create();
    TripReview::factory()->for($trip)->for($user)->create();

    expect(fn () => app(ReviewService::class)->create($trip, $user, 5, null, reviewKey()))
        ->toThrow(RuntimeException::class, 'already submitted');
});

// ── Rating validation ─────────────────────────────────────────────────────────

it('rejects rating below 1', function () {
    $trip = Trip::factory()->create(['end_date' => now()->subDay()]);
    $user = User::factory()->create();
    TripSignup::factory()->for($trip)->for($user)->confirmed()->create();

    expect(fn () => app(ReviewService::class)->create($trip, $user, 0, null, reviewKey()))
        ->toThrow(RuntimeException::class, 'between 1 and 5');
});

it('rejects rating above 5', function () {
    $trip = Trip::factory()->create(['end_date' => now()->subDay()]);
    $user = User::factory()->create();
    TripSignup::factory()->for($trip)->for($user)->confirmed()->create();

    expect(fn () => app(ReviewService::class)->create($trip, $user, 6, null, reviewKey()))
        ->toThrow(RuntimeException::class, 'between 1 and 5');
});

// ── Average rating ────────────────────────────────────────────────────────────

it('computes average rating after create', function () {
    $trip = Trip::factory()->create(['end_date' => now()->subDay()]);
    $svc  = app(ReviewService::class);

    $u1 = User::factory()->create();
    $u2 = User::factory()->create();
    TripSignup::factory()->for($trip)->for($u1)->confirmed()->create();
    TripSignup::factory()->for($trip)->for($u2)->confirmed()->create();

    $svc->create($trip, $u1, 4, null, reviewKey());
    $svc->create($trip, $u2, 2, null, reviewKey());

    expect((float) $trip->fresh()->average_rating)->toBe(3.0);
});

it('updates average rating after update', function () {
    $trip   = Trip::factory()->create(['end_date' => now()->subDay()]);
    $user   = User::factory()->create();
    $svc    = app(ReviewService::class);
    TripSignup::factory()->for($trip)->for($user)->confirmed()->create();

    $review = $svc->create($trip, $user, 2, null, reviewKey());
    expect((float) $trip->fresh()->average_rating)->toBe(2.0);

    $svc->update($review, $user, 4, null);
    expect((float) $trip->fresh()->average_rating)->toBe(4.0);
});

it('updates average rating after flag', function () {
    $trip = Trip::factory()->create(['end_date' => now()->subDay()]);
    $svc  = app(ReviewService::class);

    $u1 = User::factory()->create();
    $u2 = User::factory()->create();
    TripSignup::factory()->for($trip)->for($u1)->confirmed()->create();
    TripSignup::factory()->for($trip)->for($u2)->confirmed()->create();

    $r1 = $svc->create($trip, $u1, 5, null, reviewKey());
    $svc->create($trip, $u2, 3, null, reviewKey());
    expect((float) $trip->fresh()->average_rating)->toBe(4.0);

    $svc->flag($r1);
    // Only r2 (rating=3) is ACTIVE now
    expect((float) $trip->fresh()->average_rating)->toBe(3.0);
});

it('sets average to null when all reviews removed', function () {
    $trip   = Trip::factory()->create(['end_date' => now()->subDay()]);
    $user   = User::factory()->create();
    $svc    = app(ReviewService::class);
    TripSignup::factory()->for($trip)->for($user)->confirmed()->create();

    $review = $svc->create($trip, $user, 5, null, reviewKey());
    $svc->remove($review);

    expect($trip->fresh()->average_rating)->toBeNull();
});

// ── Flag / Remove ─────────────────────────────────────────────────────────────

it('flags a review and hides it from ACTIVE list', function () {
    $review = TripReview::factory()->create();

    app(ReviewService::class)->flag($review);

    expect($review->fresh()->status)->toBe(ReviewStatus::FLAGGED);
});

it('removes a review', function () {
    $review = TripReview::factory()->create();

    app(ReviewService::class)->remove($review);

    expect($review->fresh()->status)->toBe(ReviewStatus::REMOVED);
});

// ── Idempotency (Audit Issue 3) ───────────────────────────────────────────────

it('ReviewService::create is idempotent on idempotency_key', function () {
    $trip = Trip::factory()->create(['end_date' => now()->subDay()]);
    $user = User::factory()->create();
    TripSignup::factory()->for($trip)->for($user)->confirmed()->create();

    $key = (string) Str::uuid();
    $svc = app(ReviewService::class);

    $first  = $svc->create($trip, $user, 4, 'First text', $key);
    // A retry with the same key must return the original row, even though
    // the natural-key guard (one-review-per-user-per-trip) would otherwise
    // throw. Original fields must be preserved.
    $second = $svc->create($trip, $user, 2, 'Second text', $key);

    expect($first->id)->toBe($second->id)
        ->and($second->rating)->toBe(4)
        ->and($second->review_text)->toBe('First text')
        ->and(TripReview::count())->toBe(1);
});
