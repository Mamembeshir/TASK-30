<?php

use App\Enums\TripDifficulty;
use App\Enums\TripStatus;
use App\Enums\UserStatus;
use App\Livewire\Search\Recommendations;
use App\Livewire\Search\TripSearch;
use App\Models\SearchTerm;
use App\Models\Trip;
use App\Models\User;
use App\Models\UserSearchHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ── TripSearch renders ─────────────────────────────────────────────────────────

it('TripSearch renders for authenticated user', function () {
    $user = User::factory()->create(['status' => UserStatus::ACTIVE]);

    Livewire::actingAs($user)
        ->test(TripSearch::class)
        ->assertOk();
});

it('TripSearch displays published trips', function () {
    $user = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $trip = Trip::factory()->published()->create(['title' => 'Amazon Cardiology Mission']);

    Livewire::actingAs($user)
        ->test(TripSearch::class)
        ->assertSee('Amazon Cardiology Mission');
});

it('TripSearch hides draft trips', function () {
    $user = User::factory()->create(['status' => UserStatus::ACTIVE]);
    Trip::factory()->create(['title' => 'Secret Draft Trip', 'status' => TripStatus::DRAFT->value]);

    Livewire::actingAs($user)
        ->test(TripSearch::class)
        ->assertDontSee('Secret Draft Trip');
});

// ── Keyword search ─────────────────────────────────────────────────────────────

it('TripSearch filters results by query keyword', function () {
    $user    = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $match   = Trip::factory()->published()->create(['title' => 'Pediatric Surgery Outreach']);
    $nomatch = Trip::factory()->published()->create(['title' => 'Orthopedic Workshop']);

    Livewire::actingAs($user)
        ->test(TripSearch::class)
        ->set('query', 'Pediatric')
        ->assertSee('Pediatric Surgery Outreach')
        ->assertDontSee('Orthopedic Workshop');
});

// ── Filters ────────────────────────────────────────────────────────────────────

it('specialty filter narrows results', function () {
    $user    = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $cardio  = Trip::factory()->published()->create(['specialty' => 'Cardiology', 'title' => 'Cardio Trip']);
    $surgery = Trip::factory()->published()->create(['specialty' => 'Surgery',    'title' => 'Surgery Trip']);

    Livewire::actingAs($user)
        ->test(TripSearch::class)
        ->set('filterSpecialty', 'Cardiology')
        ->assertSee('Cardio Trip')
        ->assertDontSee('Surgery Trip');
});

it('date_from filter excludes trips starting before the date', function () {
    $user = User::factory()->create(['status' => UserStatus::ACTIVE]);
    Trip::factory()->published()->create([
        'title'      => 'Old Trip',
        'start_date' => now()->subDays(30),
        'end_date'   => now()->subDays(20),
    ]);
    $future = Trip::factory()->published()->create([
        'title'      => 'Future Trip',
        'start_date' => now()->addDays(30),
        'end_date'   => now()->addDays(40),
    ]);

    Livewire::actingAs($user)
        ->test(TripSearch::class)
        ->set('filterDateFrom', now()->addDays(10)->toDateString())
        ->assertSee('Future Trip')
        ->assertDontSee('Old Trip');
});

it('difficulty filter shows only matching trips', function () {
    $user  = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $easy  = Trip::factory()->published()->create([
        'difficulty_level' => TripDifficulty::EASY->value,
        'title' => 'Easy Trip',
    ]);
    $hard  = Trip::factory()->published()->create([
        'difficulty_level' => TripDifficulty::CHALLENGING->value,
        'title' => 'Hard Trip',
    ]);

    Livewire::actingAs($user)
        ->test(TripSearch::class)
        ->set('filterDifficulties', [TripDifficulty::EASY->value])
        ->assertSee('Easy Trip')
        ->assertDontSee('Hard Trip');
});

it('prerequisites filter shows only trips with prerequisites', function () {
    $user    = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $prereq  = Trip::factory()->published()->create([
        'prerequisites' => 'ACLS required',
        'title'         => 'Advanced Trip',
    ]);
    $noPrereq = Trip::factory()->published()->create([
        'prerequisites' => null,
        'title'         => 'Open Trip',
    ]);

    Livewire::actingAs($user)
        ->test(TripSearch::class)
        ->set('filterPrerequisites', true)
        ->assertSee('Advanced Trip')
        ->assertDontSee('Open Trip');
});

// ── Sort ────────────────────────────────────────────────────────────────────────

it('most_booked sort puts highest booking_count first', function () {
    $user    = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $popular = Trip::factory()->published()->create(['booking_count' => 99, 'title' => 'Popular Trip']);
    $quiet   = Trip::factory()->published()->create(['booking_count' => 1,  'title' => 'Quiet Trip']);

    $component = Livewire::actingAs($user)
        ->test(TripSearch::class)
        ->set('sort', 'most_booked');

    $html = $component->html();
    expect(strpos($html, 'Popular Trip'))->toBeLessThan(strpos($html, 'Quiet Trip'));
});

it('price_asc sort puts cheapest trip first', function () {
    $user      = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $cheap     = Trip::factory()->published()->create(['price_cents' => 5000,  'title' => 'Budget Trip']);
    $expensive = Trip::factory()->published()->create(['price_cents' => 50000, 'title' => 'Luxury Trip']);

    $component = Livewire::actingAs($user)
        ->test(TripSearch::class)
        ->set('sort', 'price_asc');

    $html = $component->html();
    expect(strpos($html, 'Budget Trip'))->toBeLessThan(strpos($html, 'Luxury Trip'));
});

it('highest_rated sort puts best-rated trip first', function () {
    $user = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $best = Trip::factory()->published()->create(['average_rating' => 4.9, 'title' => 'Best Trip']);
    $ok   = Trip::factory()->published()->create(['average_rating' => 3.0, 'title' => 'Okay Trip']);

    $component = Livewire::actingAs($user)
        ->test(TripSearch::class)
        ->set('sort', 'highest_rated');

    $html = $component->html();
    expect(strpos($html, 'Best Trip'))->toBeLessThan(strpos($html, 'Okay Trip'));
});

it('price_desc sort puts most expensive trip first', function () {
    $user      = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $cheap     = Trip::factory()->published()->create(['price_cents' => 5000,  'title' => 'Budget Trip']);
    $expensive = Trip::factory()->published()->create(['price_cents' => 50000, 'title' => 'Luxury Trip']);

    $component = Livewire::actingAs($user)
        ->test(TripSearch::class)
        ->set('sort', 'price_desc');

    $html = $component->html();
    expect(strpos($html, 'Luxury Trip'))->toBeLessThan(strpos($html, 'Budget Trip'));
});

// ── Type-ahead ─────────────────────────────────────────────────────────────────

it('type-ahead returns suggestions for 2+ char prefix', function () {
    $user = User::factory()->create(['status' => UserStatus::ACTIVE]);
    SearchTerm::factory()->create(['term' => 'cardiology', 'usage_count' => 50]);
    SearchTerm::factory()->create(['term' => 'cardiac surgery', 'usage_count' => 80]);

    Livewire::actingAs($user)
        ->test(TripSearch::class)
        ->call('updateTypeAhead', 'ca')   // <-- direct call; debounce bypassed in tests
        ->set('query', 'ca')
        ->call('updateTypeAhead');

    // Call directly via selectSuggestion to verify it works
    $component = Livewire::actingAs($user)->test(TripSearch::class);
    $component->set('query', 'ca');
    $component->call('updateTypeAhead');
    expect($component->get('typeAheadResults'))->not->toBeEmpty();
});

it('type-ahead returns empty for single char query', function () {
    $user = User::factory()->create(['status' => UserStatus::ACTIVE]);
    SearchTerm::factory()->create(['term' => 'cardiology']);

    $component = Livewire::actingAs($user)->test(TripSearch::class);
    $component->set('query', 'c');
    $component->call('updateTypeAhead');

    expect($component->get('typeAheadResults'))->toBe([]);
});

it('selectSuggestion sets query and clears type-ahead', function () {
    $user = User::factory()->create(['status' => UserStatus::ACTIVE]);

    $component = Livewire::actingAs($user)->test(TripSearch::class);
    $component->set('typeAheadResults', [['term' => 'cardiology', 'category' => 'specialty']]);
    $component->call('selectSuggestion', 'cardiology');

    expect($component->get('query'))->toBe('cardiology')
        ->and($component->get('typeAheadResults'))->toBe([]);
});

// ── Search history ─────────────────────────────────────────────────────────────

it('search records history when user is authenticated', function () {
    $user = User::factory()->create(['status' => UserStatus::ACTIVE]);
    Trip::factory()->published()->create();

    Livewire::actingAs($user)
        ->test(TripSearch::class)
        ->set('query', 'surgery outreach');

    expect(UserSearchHistory::where('user_id', $user->id)->exists())->toBeTrue();
});

it('clearHistory removes all entries and dispatches notify', function () {
    $user = User::factory()->create(['status' => UserStatus::ACTIVE]);
    UserSearchHistory::factory()->count(5)->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(TripSearch::class)
        ->call('clearHistory')
        ->assertDispatched('notify');

    expect(UserSearchHistory::where('user_id', $user->id)->count())->toBe(0);
});

// ── Recommendations ────────────────────────────────────────────────────────────

it('Recommendations renders for authenticated user', function () {
    $user = User::factory()->create(['status' => UserStatus::ACTIVE]);

    Livewire::actingAs($user)
        ->test(Recommendations::class)
        ->assertOk();
});

it('Recommendations shows labeled sections for trips with data', function () {
    $user = User::factory()->create(['status' => UserStatus::ACTIVE]);
    Trip::factory()->published()->create([
        'booking_count' => 20,
        'created_at'    => now()->subDays(30),
        'start_date'    => now()->addDays(15),
        'end_date'      => now()->addDays(25),
    ]);

    Livewire::actingAs($user)
        ->test(Recommendations::class)
        ->assertSeeHtml('Popular This Quarter');
});

it('Recommendations shows empty state when no trips exist', function () {
    $user = User::factory()->create(['status' => UserStatus::ACTIVE]);

    Livewire::actingAs($user)
        ->test(Recommendations::class)
        ->assertSee('No recommendations available');
});

// ── Observer: Trip creates terms ───────────────────────────────────────────────

it('creating a trip populates SearchTerm with specialty and destination', function () {
    Trip::factory()->published()->create([
        'specialty'   => 'Dermatology',
        'destination' => 'Barcelona, Spain',
    ]);

    expect(SearchTerm::where('term', 'dermatology')->exists())->toBeTrue()
        ->and(SearchTerm::where('term', 'barcelona, spain')->exists())->toBeTrue();
});

it('updating a trip updates SearchTerm entries', function () {
    $trip = Trip::factory()->published()->create(['specialty' => 'Cardiology']);

    $trip->specialty = 'Rheumatology';
    $trip->save();

    expect(SearchTerm::where('term', 'rheumatology')->exists())->toBeTrue();
});
