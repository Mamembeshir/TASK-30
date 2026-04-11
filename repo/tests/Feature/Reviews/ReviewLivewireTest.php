<?php

use App\Enums\ReviewStatus;
use App\Enums\UserRole as UserRoleEnum;
use App\Enums\UserStatus;
use App\Livewire\Reviews\ReviewForm;
use App\Livewire\Reviews\ReviewModeration;
use App\Livewire\Reviews\TripReviews;
use App\Models\Trip;
use App\Models\TripReview;
use App\Models\TripSignup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ── TripReviews component ─────────────────────────────────────────────────────

it('renders active reviews on the trip page', function () {
    $trip   = Trip::factory()->create(['end_date' => now()->subDay()]);
    $review = TripReview::factory()->for($trip)->create(['review_text' => 'Amazing experience!']);

    Livewire::test(TripReviews::class, ['trip' => $trip])
        ->assertSee('Amazing experience!');
});

it('does not show flagged reviews', function () {
    $trip   = Trip::factory()->create(['end_date' => now()->subDay()]);
    $review = TripReview::factory()->for($trip)->flagged()->create(['review_text' => 'Hidden review']);

    Livewire::test(TripReviews::class, ['trip' => $trip])
        ->assertDontSee('Hidden review');
});

it('shows Write a Review button when user is eligible', function () {
    $trip = Trip::factory()->create(['end_date' => now()->subDay()]);
    $user = User::factory()->create(['status' => UserStatus::ACTIVE]);
    TripSignup::factory()->for($trip)->for($user)->confirmed()->create();

    Livewire::actingAs($user)
        ->test(TripReviews::class, ['trip' => $trip])
        ->assertSee('Write a Review');
});

it('hides Write a Review when user has no confirmed signup', function () {
    $trip = Trip::factory()->create(['end_date' => now()->subDay()]);
    $user = User::factory()->create(['status' => UserStatus::ACTIVE]);

    Livewire::actingAs($user)
        ->test(TripReviews::class, ['trip' => $trip])
        ->assertDontSee('Write a Review');
});

// ── ReviewForm component ──────────────────────────────────────────────────────

it('submits a review via the form', function () {
    $trip = Trip::factory()->create(['end_date' => now()->subDay()]);
    $user = User::factory()->create(['status' => UserStatus::ACTIVE]);
    TripSignup::factory()->for($trip)->for($user)->confirmed()->create();

    Livewire::actingAs($user)
        ->test(ReviewForm::class, ['trip' => $trip])
        ->call('setRating', 5)
        ->set('reviewText', 'Outstanding!')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect();

    expect(TripReview::where('trip_id', $trip->id)->where('user_id', $user->id)->exists())->toBeTrue();
});

it('validates rating is required', function () {
    $trip = Trip::factory()->create(['end_date' => now()->subDay()]);
    $user = User::factory()->create(['status' => UserStatus::ACTIVE]);
    TripSignup::factory()->for($trip)->for($user)->confirmed()->create();

    Livewire::actingAs($user)
        ->test(ReviewForm::class, ['trip' => $trip])
        ->set('rating', 0)
        ->call('submit')
        ->assertHasErrors(['rating']);
});

it('rejects review text over 2000 characters', function () {
    $trip = Trip::factory()->create(['end_date' => now()->subDay()]);
    $user = User::factory()->create(['status' => UserStatus::ACTIVE]);
    TripSignup::factory()->for($trip)->for($user)->confirmed()->create();

    Livewire::actingAs($user)
        ->test(ReviewForm::class, ['trip' => $trip])
        ->call('setRating', 4)
        ->set('reviewText', str_repeat('a', 2001))
        ->call('submit')
        ->assertHasErrors(['reviewText']);
});

// ── ReviewModeration component ────────────────────────────────────────────────

it('renders review moderation for admin', function () {
    $admin  = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $admin->addRole(UserRoleEnum::ADMIN);
    $review = TripReview::factory()->create(['review_text' => 'Admin can see this']);

    Livewire::actingAs($admin)
        ->test(ReviewModeration::class)
        ->assertOk()
        ->assertSee('Admin can see this');
});

it('admin can flag a review', function () {
    $admin  = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $admin->addRole(UserRoleEnum::ADMIN);
    $review = TripReview::factory()->create();

    Livewire::actingAs($admin)
        ->test(ReviewModeration::class)
        ->call('flag', $review->id);

    expect($review->fresh()->status)->toBe(ReviewStatus::FLAGGED);
});

it('admin can remove a review', function () {
    $admin  = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $admin->addRole(UserRoleEnum::ADMIN);
    $review = TripReview::factory()->create();

    Livewire::actingAs($admin)
        ->test(ReviewModeration::class)
        ->call('remove', $review->id);

    expect($review->fresh()->status)->toBe(ReviewStatus::REMOVED);
});

it('non-admin cannot access review moderation', function () {
    $user = User::factory()->create(['status' => UserStatus::ACTIVE]);

    $this->actingAs($user)
         ->get(route('admin.reviews'))
         ->assertForbidden();
});
