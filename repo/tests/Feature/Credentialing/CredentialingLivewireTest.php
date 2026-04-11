<?php

use App\Enums\CaseStatus;
use App\Enums\CredentialingStatus;
use App\Enums\DocumentType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Livewire\Credentialing\CaseDetail;
use App\Livewire\Credentialing\CaseList;
use App\Livewire\Credentialing\DoctorProfile;
use App\Models\CredentialingCase;
use App\Models\Doctor;
use App\Models\DoctorDocument;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function credReviewer(): User
{
    $user = User::factory()->create(['status' => UserStatus::ACTIVE]);
    UserProfile::create(['user_id' => $user->id, 'first_name' => 'Reviewer', 'last_name' => 'User']);
    $user->addRole(UserRole::CREDENTIALING_REVIEWER);
    return $user->fresh();
}

function doctorUser(): array
{
    $user = User::factory()->create(['status' => UserStatus::ACTIVE]);
    UserProfile::create(['user_id' => $user->id, 'first_name' => 'Dr', 'last_name' => 'Smith']);
    $user->addRole(UserRole::DOCTOR);
    $user->addRole(UserRole::MEMBER);
    $doctor = Doctor::factory()->create([
        'user_id'              => $user->id,
        'credentialing_status' => CredentialingStatus::NOT_SUBMITTED->value,
    ]);
    return [$user->fresh(), $doctor->fresh()];
}

// ── CaseList ───────────────────────────────────────────────────────────────────

it('CaseList renders for reviewer', function () {
    Livewire::actingAs(credReviewer())
        ->test(CaseList::class)
        ->assertOk();
});

it('CaseList is forbidden for a plain member', function () {
    $member = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $member->addRole(UserRole::MEMBER);

    Livewire::actingAs($member->fresh())
        ->test(CaseList::class)
        ->assertForbidden();
});

it('CaseList shows submitted cases', function () {
    $reviewer = credReviewer();
    $case = CredentialingCase::factory()->create(['status' => CaseStatus::SUBMITTED]);

    Livewire::actingAs($reviewer)
        ->test(CaseList::class)
        ->assertSee('Submitted'); // status badge label
});

// ── CaseDetail ─────────────────────────────────────────────────────────────────

it('CaseDetail renders for reviewer', function () {
    $reviewer = credReviewer();
    $case = CredentialingCase::factory()->create(['status' => CaseStatus::SUBMITTED]);

    Livewire::actingAs($reviewer)
        ->test(CaseDetail::class, ['case' => $case])
        ->assertOk();
});

it('CaseDetail is forbidden for a plain member', function () {
    $member = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $member->addRole(UserRole::MEMBER);
    $case = CredentialingCase::factory()->create();

    Livewire::actingAs($member->fresh())
        ->test(CaseDetail::class, ['case' => $case])
        ->assertForbidden();
});

it('CaseDetail assignReviewer action assigns the reviewer', function () {
    $reviewer = credReviewer();
    $case     = CredentialingCase::factory()->create(['status' => CaseStatus::SUBMITTED]);

    Livewire::actingAs($reviewer)
        ->test(CaseDetail::class, ['case' => $case])
        ->set('selectedReviewerId', $reviewer->id)
        ->call('assignReviewer');

    expect($case->fresh()->assigned_reviewer)->toBe($reviewer->id);
});

// ── DoctorProfile ──────────────────────────────────────────────────────────────

it('DoctorProfile renders for a doctor user', function () {
    [$user, $doctor] = doctorUser();

    Livewire::actingAs($user)
        ->test(DoctorProfile::class)
        ->assertOk();
});

it('DoctorProfile is forbidden for a user without doctor profile', function () {
    $member = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $member->addRole(UserRole::MEMBER);

    Livewire::actingAs($member->fresh())
        ->test(DoctorProfile::class)
        ->assertForbidden();
});

it('DoctorProfile submitCase shows error without required documents', function () {
    [$user, $doctor] = doctorUser();

    Livewire::actingAs($user)
        ->test(DoctorProfile::class)
        ->call('submitCase')
        ->assertHasErrors(['submit']);
});

it('DoctorProfile submitCase succeeds with required documents', function () {
    [$user, $doctor] = doctorUser();

    DoctorDocument::factory()->create(['doctor_id' => $doctor->id, 'document_type' => DocumentType::LICENSE,              'uploaded_by' => $user->id]);
    DoctorDocument::factory()->create(['doctor_id' => $doctor->id, 'document_type' => DocumentType::BOARD_CERTIFICATION, 'uploaded_by' => $user->id]);

    Livewire::actingAs($user)
        ->test(DoctorProfile::class)
        ->call('submitCase');

    expect(\App\Models\CredentialingCase::where('doctor_id', $doctor->id)->exists())->toBeTrue();
});
