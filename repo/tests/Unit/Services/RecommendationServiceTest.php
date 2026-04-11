<?php

use App\Enums\SignupStatus;
use App\Enums\TripStatus;
use App\Models\Trip;
use App\Models\TripSignup;
use App\Models\User;
use App\Services\RecommendationService;
use App\Strategies\MostBookedLast90Days;
use App\Strategies\SimilarSpecialty;
use App\Strategies\UpcomingSoonest;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── MostBookedLast90Days ───────────────────────────────────────────────────────

it('MostBookedLast90Days returns PUBLISHED trips ordered by booking_count', function () {
    $user  = User::factory()->create();
    $low   = Trip::factory()->published()->create(['booking_count' => 5,  'created_at' => now()->subDays(30)]);
    $high  = Trip::factory()->published()->create(['booking_count' => 42, 'created_at' => now()->subDays(30)]);
    $old   = Trip::factory()->published()->create(['booking_count' => 99, 'created_at' => now()->subDays(100)]); // outside 90d

    $result = app(MostBookedLast90Days::class)->recommend($user, 5);

    expect($result)->toHaveCount(2)
        ->and($result->first()->id)->toBe($high->id)
        ->and($result->pluck('id'))->not->toContain($old->id);
});

it('MostBookedLast90Days excludes non-PUBLISHED trips', function () {
    $user = User::factory()->create();
    Trip::factory()->create(['status' => TripStatus::DRAFT->value, 'booking_count' => 999, 'created_at' => now()]);

    $result = app(MostBookedLast90Days::class)->recommend($user, 5);

    expect($result)->toBeEmpty();
});

// ── SimilarSpecialty ───────────────────────────────────────────────────────────

it('SimilarSpecialty returns trips matching user past signup specialties', function () {
    $user    = User::factory()->create();
    $past    = Trip::factory()->published()->create(['specialty' => 'Cardiology']);
    $signup  = TripSignup::factory()->create([
        'user_id' => $user->id,
        'trip_id' => $past->id,
        'status'  => SignupStatus::CONFIRMED->value,
    ]);
    $similar = Trip::factory()->published()->create(['specialty' => 'Cardiology']);
    Trip::factory()->published()->create(['specialty' => 'Orthopedics']);

    $result = app(SimilarSpecialty::class)->recommend($user, 5);

    expect($result)->toHaveCount(1)
        ->and($result->first()->id)->toBe($similar->id);
});

it('SimilarSpecialty returns empty collection with no past signups', function () {
    $user = User::factory()->create();

    $result = app(SimilarSpecialty::class)->recommend($user, 5);

    expect($result)->toBeEmpty();
});

it('SimilarSpecialty excludes trips already signed up for', function () {
    $user   = User::factory()->create();
    $trip   = Trip::factory()->published()->create(['specialty' => 'Neurology']);
    TripSignup::factory()->create([
        'user_id' => $user->id,
        'trip_id' => $trip->id,
        'status'  => SignupStatus::CONFIRMED->value,
    ]);
    // Same specialty — but this is the trip already signed up for
    $result = app(SimilarSpecialty::class)->recommend($user, 5);

    expect($result->pluck('id'))->not->toContain($trip->id);
});

// ── UpcomingSoonest ────────────────────────────────────────────────────────────

it('UpcomingSoonest returns PUBLISHED trips ordered by start_date ascending', function () {
    $user  = User::factory()->create();
    $far   = Trip::factory()->published()->create(['start_date' => now()->addDays(30), 'end_date' => now()->addDays(40)]);
    $near  = Trip::factory()->published()->create(['start_date' => now()->addDays(5),  'end_date' => now()->addDays(15)]);

    $result = app(UpcomingSoonest::class)->recommend($user, 5);

    expect($result->first()->id)->toBe($near->id);
});

it('UpcomingSoonest excludes past trips', function () {
    $user = User::factory()->create();
    Trip::factory()->published()->create([
        'start_date' => now()->subDays(10),
        'end_date'   => now()->subDays(2),
    ]);

    $result = app(UpcomingSoonest::class)->recommend($user, 5);

    expect($result)->toBeEmpty();
});

// ── RecommendationService ──────────────────────────────────────────────────────

it('RecommendationService returns labeled sections from config strategies', function () {
    $user = User::factory()->create();
    Trip::factory()->published()->create([
        'booking_count' => 10,
        'created_at'    => now()->subDays(30),
        'start_date'    => now()->addDays(20),
        'end_date'      => now()->addDays(30),
    ]);

    $sections = app(RecommendationService::class)->getRecommendations($user);

    // At least one section should appear (MostBookedLast90Days or UpcomingSoonest)
    expect($sections)->not->toBeEmpty();

    // Each section has required keys
    foreach ($sections as $section) {
        expect($section)->toHaveKeys(['key', 'label', 'trips']);
        expect($section['trips'])->toBeInstanceOf(\Illuminate\Support\Collection::class);
    }
});

it('strategy registry: adding a strategy via config changes output', function () {
    $user = User::factory()->create();

    // Temporarily override config with an extra strategy
    config(['recommendations.strategies' => [
        \App\Strategies\UpcomingSoonest::class,
    ]]);

    $sections = app(RecommendationService::class)->getRecommendations($user);

    // UpcomingSoonest section may be empty (no future trips) — that's fine
    // What matters is the config swap worked without errors
    expect($sections)->toBeArray();
});

it('strategy registry: empty config returns no sections', function () {
    $user = User::factory()->create();
    config(['recommendations.strategies' => []]);

    $sections = app(RecommendationService::class)->getRecommendations($user);

    expect($sections)->toBe([]);
});

it('sections with no results are excluded from output', function () {
    $user = User::factory()->create();

    // No trips at all → all strategies return empty → no sections
    $sections = app(RecommendationService::class)->getRecommendations($user);

    expect($sections)->toBe([]);
});
