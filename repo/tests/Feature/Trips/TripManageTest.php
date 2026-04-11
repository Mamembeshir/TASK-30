<?php

use App\Enums\CredentialingStatus;
use App\Enums\SignupStatus;
use App\Enums\TripStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Livewire\Trips\SignupWizard;
use App\Livewire\Trips\TripManage;
use App\Models\Doctor;
use App\Models\Trip;
use App\Models\TripSignup;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function tripAdmin(): User
{
    $user = User::factory()->create(['status' => UserStatus::ACTIVE]);
    UserProfile::create(['user_id' => $user->id, 'first_name' => 'Admin', 'last_name' => 'Trip']);
    $user->addRole(UserRole::ADMIN);
    return $user->fresh();
}

function approvedDoctor(): Doctor
{
    return Doctor::factory()->create(['credentialing_status' => CredentialingStatus::APPROVED->value]);
}

// ── TripManage: role visibility ────────────────────────────────────────────────

it('TripManage renders for admin', function () {
    Livewire::actingAs(tripAdmin())
        ->test(TripManage::class)
        ->assertOk();
});

it('TripManage is forbidden for a plain member', function () {
    $member = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $member->addRole(UserRole::MEMBER);

    Livewire::actingAs($member->fresh())
        ->test(TripManage::class)
        ->assertForbidden();
});

// ── TripManage: create action ──────────────────────────────────────────────────

it('TripManage save creates a DRAFT trip', function () {
    $admin  = tripAdmin();
    $doctor = approvedDoctor();

    Livewire::actingAs($admin)
        ->test(TripManage::class)
        ->set('title', 'New Surgery Trip')
        ->set('description', 'A great trip')
        ->set('leadDoctorId', $doctor->id)
        ->set('specialty', 'Surgery')
        ->set('destination', 'Nairobi, Kenya')
        ->set('startDate', now()->addMonths(2)->toDateString())
        ->set('endDate', now()->addMonths(2)->addWeek()->toDateString())
        ->set('difficultyLevel', 'MODERATE')
        ->set('totalSeats', 10)
        ->set('priceCents', 100000)
        ->call('save');

    expect(Trip::where('title', 'New Surgery Trip')->where('status', TripStatus::DRAFT)->exists())->toBeTrue();
});

it('TripManage save validates required fields', function () {
    Livewire::actingAs(tripAdmin())
        ->test(TripManage::class)
        ->call('save')
        ->assertHasErrors(['title', 'leadDoctorId', 'specialty', 'destination', 'startDate', 'endDate']);
});

// ── TripManage: publish action ─────────────────────────────────────────────────

it('TripManage publish transitions DRAFT to PUBLISHED', function () {
    $admin  = tripAdmin();
    $doctor = approvedDoctor();
    $trip   = Trip::factory()->create(['lead_doctor_id' => $doctor->id, 'status' => TripStatus::DRAFT->value, 'version' => 1]);

    Livewire::actingAs($admin)
        ->test(TripManage::class, ['trip' => $trip])
        ->call('publish');

    expect($trip->fresh()->status)->toBe(TripStatus::PUBLISHED);
});

// ── SignupWizard ───────────────────────────────────────────────────────────────

it('SignupWizard renders for the signup owner', function () {
    $doctor = approvedDoctor();
    $trip   = Trip::factory()->published()->create(['lead_doctor_id' => $doctor->id]);
    $user   = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $user->addRole(UserRole::MEMBER);
    UserProfile::create(['user_id' => $user->id, 'first_name' => 'Test', 'last_name' => 'Member']);

    $signup = TripSignup::factory()->for($trip)->for($user->fresh())->create([
        'status'          => SignupStatus::HOLD->value,
        'hold_expires_at' => now()->addMinutes(10),
        'version'         => 1,
    ]);

    Livewire::actingAs($user->fresh())
        ->test(SignupWizard::class, ['trip' => $trip, 'signup' => $signup])
        ->assertOk()
        ->assertSee($trip->title);
});

it('SignupWizard is forbidden for a different user', function () {
    $doctor  = approvedDoctor();
    $trip    = Trip::factory()->published()->create(['lead_doctor_id' => $doctor->id]);
    $owner   = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $other   = User::factory()->create(['status' => UserStatus::ACTIVE]);

    $signup  = TripSignup::factory()->for($trip)->for($owner)->create([
        'status'          => SignupStatus::HOLD->value,
        'hold_expires_at' => now()->addMinutes(10),
        'version'         => 1,
    ]);

    Livewire::actingAs($other)
        ->test(SignupWizard::class, ['trip' => $trip, 'signup' => $signup])
        ->assertForbidden();
});
