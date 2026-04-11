<?php

use App\Enums\UserStatus;
use App\Livewire\Auth\Profile;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('renders profile page for authenticated user', function () {
    $user = User::factory()->create(['status' => UserStatus::ACTIVE]);
    UserProfile::create(['user_id' => $user->id, 'first_name' => 'Jane', 'last_name' => 'Doe']);

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->assertOk();
});

it('redirects unauthenticated user from profile to login', function () {
    $this->get(route('profile'))->assertRedirect(route('login'));
});
