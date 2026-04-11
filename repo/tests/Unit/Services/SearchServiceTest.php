<?php

use App\Enums\TripDifficulty;
use App\Enums\TripStatus;
use App\Models\SearchTerm;
use App\Models\Trip;
use App\Models\User;
use App\Models\UserSearchHistory;
use App\Services\SearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── SRCH-01: Keyword search ────────────────────────────────────────────────────

it('returns published trips matching keyword in title', function () {
    $match = Trip::factory()->published()->create(['title' => 'Cardiology Expedition 2026', 'specialty' => 'Cardiology']);
    $other = Trip::factory()->published()->create(['title' => 'Orthopedic Mission', 'specialty' => 'Surgery']);

    $results = app(SearchService::class)->search('Cardiology', [], 'newest', null);

    expect($results->total())->toBe(1)
        ->and($results->first()->id)->toBe($match->id);
});

it('returns published trips matching keyword in destination', function () {
    $match = Trip::factory()->published()->create(['destination' => 'Cairo, Egypt']);
    Trip::factory()->published()->create(['destination' => 'Tokyo, Japan']);

    $results = app(SearchService::class)->search('Cairo', [], 'newest', null);

    expect($results->total())->toBe(1)
        ->and($results->first()->id)->toBe($match->id);
});

it('returns published trips matching keyword in specialty', function () {
    $match = Trip::factory()->published()->create(['specialty' => 'Neurology']);
    Trip::factory()->published()->create(['specialty' => 'Cardiology']);

    $results = app(SearchService::class)->search('Neurology', [], 'newest', null);

    expect($results->total())->toBe(1);
});

it('excludes DRAFT trips from search results', function () {
    Trip::factory()->create(['title' => 'Draft Neurology Trip', 'status' => TripStatus::DRAFT->value]);

    $results = app(SearchService::class)->search('Neurology', [], 'newest', null);

    expect($results->total())->toBe(0);
});

it('returns all published trips when query is empty', function () {
    Trip::factory()->published()->count(5)->create();
    Trip::factory()->count(2)->create(); // DRAFT — excluded

    $results = app(SearchService::class)->search('', [], 'newest', null);

    expect($results->total())->toBe(5);
});

// ── SRCH-02: Filters ───────────────────────────────────────────────────────────

it('filters by specialty', function () {
    $cardio  = Trip::factory()->published()->create(['specialty' => 'Cardiology']);
    $surgery = Trip::factory()->published()->create(['specialty' => 'Surgery']);

    $results = app(SearchService::class)->search('', ['specialty' => 'Cardiology'], 'newest', null);

    expect($results->total())->toBe(1)
        ->and($results->first()->id)->toBe($cardio->id);
});

it('filters by date range: date_from', function () {
    $future = Trip::factory()->published()->create([
        'start_date' => now()->addDays(30),
        'end_date'   => now()->addDays(40),
    ]);
    $past = Trip::factory()->published()->create([
        'start_date' => now()->subDays(30),
        'end_date'   => now()->subDays(20),
    ]);

    $results = app(SearchService::class)->search('', [
        'date_from' => now()->addDays(20)->toDateString(),
    ], 'newest', null);

    expect($results->total())->toBe(1)
        ->and($results->first()->id)->toBe($future->id);
});

it('filters by date range: date_to', function () {
    Trip::factory()->published()->create([
        'start_date' => now()->addDays(30),
        'end_date'   => now()->addDays(90),
    ]);
    $near = Trip::factory()->published()->create([
        'start_date' => now()->addDays(5),
        'end_date'   => now()->addDays(15),
    ]);

    $results = app(SearchService::class)->search('', [
        'date_to' => now()->addDays(30)->toDateString(),
    ], 'newest', null);

    expect($results->total())->toBe(1)
        ->and($results->first()->id)->toBe($near->id);
});

it('filters by difficulty', function () {
    $easy      = Trip::factory()->published()->create(['difficulty_level' => TripDifficulty::EASY->value]);
    $challenge = Trip::factory()->published()->create(['difficulty_level' => TripDifficulty::CHALLENGING->value]);

    $results = app(SearchService::class)->search('', [
        'difficulties' => [TripDifficulty::EASY->value],
    ], 'newest', null);

    expect($results->total())->toBe(1)
        ->and($results->first()->id)->toBe($easy->id);
});

it('filters by has_prerequisites', function () {
    $prereq = Trip::factory()->published()->create(['prerequisites' => 'Must have ACLS certification']);
    $none   = Trip::factory()->published()->create(['prerequisites' => null]);

    $results = app(SearchService::class)->search('', ['has_prerequisites' => true], 'newest', null);

    expect($results->total())->toBe(1)
        ->and($results->first()->id)->toBe($prereq->id);
});

// ── SRCH-03: Sort ──────────────────────────────────────────────────────────────

it('sorts by most_booked returns highest booking_count first', function () {
    $low  = Trip::factory()->published()->create(['booking_count' => 5]);
    $high = Trip::factory()->published()->create(['booking_count' => 42]);

    $results = app(SearchService::class)->search('', [], 'most_booked', null);

    expect($results->first()->id)->toBe($high->id);
});

it('sorts by price_asc returns cheapest first', function () {
    $expensive = Trip::factory()->published()->create(['price_cents' => 500000]);
    $cheap     = Trip::factory()->published()->create(['price_cents' => 10000]);

    $results = app(SearchService::class)->search('', [], 'price_asc', null);

    expect($results->first()->id)->toBe($cheap->id);
});

it('sorts by price_desc returns most expensive first', function () {
    $expensive = Trip::factory()->published()->create(['price_cents' => 500000]);
    $cheap     = Trip::factory()->published()->create(['price_cents' => 10000]);

    $results = app(SearchService::class)->search('', [], 'price_desc', null);

    expect($results->first()->id)->toBe($expensive->id);
});

it('sorts by newest returns most recently created first', function () {
    $old  = Trip::factory()->published()->create(['created_at' => now()->subDays(10)]);
    $new  = Trip::factory()->published()->create(['created_at' => now()]);

    $results = app(SearchService::class)->search('', [], 'newest', null);

    expect($results->first()->id)->toBe($new->id);
});

// ── SRCH-04: Type-ahead ────────────────────────────────────────────────────────

it('type-ahead returns top 5 matching terms by usage_count', function () {
    SearchTerm::factory()->create(['term' => 'cardiology', 'usage_count' => 100]);
    SearchTerm::factory()->create(['term' => 'cardiac care', 'usage_count' => 200]);
    SearchTerm::factory()->create(['term' => 'cardiothoracic', 'usage_count' => 50]);

    $suggestions = app(SearchService::class)->typeAhead('card');

    expect($suggestions)->toHaveCount(3)
        ->and($suggestions[0]['term'])->toBe('cardiac care'); // highest usage_count first
});

it('type-ahead returns empty array for prefix shorter than 2 chars', function () {
    SearchTerm::factory()->create(['term' => 'cardiology']);

    expect(app(SearchService::class)->typeAhead('c'))->toBe([]);
    expect(app(SearchService::class)->typeAhead(''))->toBe([]);
});

it('type-ahead is case-insensitive', function () {
    SearchTerm::factory()->create(['term' => 'cardiology', 'usage_count' => 10]);

    $results = app(SearchService::class)->typeAhead('CARD');

    expect($results)->toHaveCount(1)
        ->and($results[0]['term'])->toBe('cardiology');
});

it('type-ahead returns at most 5 suggestions', function () {
    SearchTerm::factory()->count(10)->create([
        'term' => fn () => 'neuro' . fake()->unique()->lexify('???'),
    ]);

    $results = app(SearchService::class)->typeAhead('neuro');

    expect($results)->toHaveCount(5);
});

// ── SRCH-05: History ───────────────────────────────────────────────────────────

it('records search in user history', function () {
    $user = User::factory()->create();
    Trip::factory()->published()->create(['title' => 'History Test Trip']);

    app(SearchService::class)->search('History Test', [], 'newest', $user);

    $history = UserSearchHistory::where('user_id', $user->id)->get();
    expect($history)->toHaveCount(1)
        ->and($history->first()->query)->toBe('History Test');
});

it('history is capped at 20 entries', function () {
    $user = User::factory()->create();

    // Seed 22 searches
    UserSearchHistory::factory()->count(22)->create(['user_id' => $user->id]);

    // One more search through the service
    app(SearchService::class)->search('extra search', [], 'newest', $user);

    $count = UserSearchHistory::where('user_id', $user->id)->count();
    expect($count)->toBeLessThanOrEqual(20);
});

it('getUserHistory returns last 20 entries in reverse chronological order', function () {
    $user = User::factory()->create();
    UserSearchHistory::factory()->count(5)->create(['user_id' => $user->id, 'searched_at' => now()]);

    $history = app(SearchService::class)->getUserHistory($user);

    expect($history)->toHaveCount(5);
});

it('clearHistory deletes all entries for user', function () {
    $user  = User::factory()->create();
    $other = User::factory()->create();
    UserSearchHistory::factory()->count(5)->create(['user_id' => $user->id]);
    UserSearchHistory::factory()->count(3)->create(['user_id' => $other->id]);

    app(SearchService::class)->clearHistory($user);

    expect(UserSearchHistory::where('user_id', $user->id)->count())->toBe(0);
    expect(UserSearchHistory::where('user_id', $other->id)->count())->toBe(3); // untouched
});

it('search does not record history for unauthenticated users', function () {
    Trip::factory()->published()->create(['title' => 'No auth trip']);

    app(SearchService::class)->search('No auth', [], 'newest', null);

    expect(UserSearchHistory::count())->toBe(0);
});
