<?php

use App\Enums\CaseStatus;
use App\Enums\DocumentType;
use App\Enums\UserRole;
use App\Models\CredentialingCase;
use App\Models\Doctor;
use App\Models\DoctorDocument;
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

function apiReviewer(): User
{
    $user = User::factory()->create();
    UserProfile::create(['user_id' => $user->id, 'first_name' => 'Reviewer', 'last_name' => 'Staff']);
    $user->addRole(UserRole::CREDENTIALING_REVIEWER);
    return $user->fresh();
}

function apiSubmittedCase(): CredentialingCase
{
    $doctor = Doctor::factory()->create();
    DoctorDocument::factory()->create(['doctor_id' => $doctor->id, 'document_type' => DocumentType::LICENSE]);
    DoctorDocument::factory()->create(['doctor_id' => $doctor->id, 'document_type' => DocumentType::BOARD_CERTIFICATION]);

    return CredentialingCase::factory()->create([
        'doctor_id' => $doctor->id,
        'status'    => CaseStatus::SUBMITTED->value,
    ]);
}

// ── POST /api/credentialing/cases/{case}/assign ────────────────────────────────

it('POST /api/credentialing/cases/{case}/assign assigns a reviewer', function () {
    $actor    = apiReviewer();
    $reviewer = apiReviewer();
    $case     = apiSubmittedCase();

    $this->actingAs($actor)
        ->postJson("/api/credentialing/cases/{$case->id}/assign", [
            'reviewer_id' => $reviewer->id,
        ])
        ->assertOk()
        ->assertJsonPath('assigned_reviewer', $reviewer->id);
});

it('POST /api/credentialing/cases/{case}/assign returns 422 for non-SUBMITTED case', function () {
    $actor    = apiReviewer();
    $reviewer = apiReviewer();
    $case     = CredentialingCase::factory()->inReview()->create();

    $case->update(['assigned_reviewer' => $reviewer->id]);

    $this->actingAs($actor)
        ->postJson("/api/credentialing/cases/{$case->id}/assign", [
            'reviewer_id' => $reviewer->id,
        ])
        ->assertStatus(422);
});

it('POST /api/credentialing/cases/{case}/assign returns 422 when reviewer_id lacks reviewer role', function () {
    $actor      = apiReviewer();
    $plainUser  = User::factory()->create();
    UserProfile::create(['user_id' => $plainUser->id, 'first_name' => 'Plain', 'last_name' => 'User']);
    $plainUser->addRole(UserRole::MEMBER);
    $case = apiSubmittedCase();

    $this->actingAs($actor)
        ->postJson("/api/credentialing/cases/{$case->id}/assign", [
            'reviewer_id' => $plainUser->id,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('reviewer_id');
});

it('POST /api/credentialing/cases/{case}/assign returns 403 for plain member', function () {
    $member   = User::factory()->create();
    $member->addRole(UserRole::MEMBER);
    $reviewer = apiReviewer();
    $case     = apiSubmittedCase();

    $this->actingAs($member->fresh())
        ->postJson("/api/credentialing/cases/{$case->id}/assign", [
            'reviewer_id' => $reviewer->id,
        ])
        ->assertForbidden();
});

// ── POST /api/credentialing/cases/{case}/approve ───────────────────────────────

it('POST /api/credentialing/cases/{case}/approve approves an INITIAL_REVIEW case', function () {
    $reviewer = apiReviewer();
    $case     = CredentialingCase::factory()->inReview()->create([
        'assigned_reviewer' => $reviewer->id,
    ]);

    $this->actingAs($reviewer)
        ->postJson("/api/credentialing/cases/{$case->id}/approve")
        ->assertOk()
        ->assertJsonPath('status', CaseStatus::APPROVED->value);
});

it('POST /api/credentialing/cases/{case}/approve returns 403 when actor is not assigned reviewer', function () {
    $assigned   = apiReviewer();
    $unassigned = apiReviewer();

    $case = CredentialingCase::factory()->inReview()->create([
        'assigned_reviewer' => $assigned->id,
    ]);

    $this->actingAs($unassigned)
        ->postJson("/api/credentialing/cases/{$case->id}/approve")
        ->assertForbidden();
});

// ── POST /api/credentialing/cases/{case}/reject ────────────────────────────────

it('POST /api/credentialing/cases/{case}/reject rejects an INITIAL_REVIEW case', function () {
    $reviewer = apiReviewer();
    $case     = CredentialingCase::factory()->inReview()->create([
        'assigned_reviewer' => $reviewer->id,
    ]);

    $this->actingAs($reviewer)
        ->postJson("/api/credentialing/cases/{$case->id}/reject", [
            'notes' => 'Documents are not legible enough to verify credentials.',
        ])
        ->assertOk()
        ->assertJsonPath('status', CaseStatus::REJECTED->value);
});

it('POST /api/credentialing/cases/{case}/reject returns 422 when notes too short', function () {
    $reviewer = apiReviewer();
    $case     = CredentialingCase::factory()->inReview()->create([
        'assigned_reviewer' => $reviewer->id,
    ]);

    $this->actingAs($reviewer)
        ->postJson("/api/credentialing/cases/{$case->id}/reject", [
            'notes' => 'Too short',
        ])
        ->assertStatus(422);
});

// ── POST /api/credentialing/cases/{case}/start-review ─────────────────────────

it('POST /api/credentialing/cases/{case}/start-review moves SUBMITTED case to INITIAL_REVIEW', function () {
    $reviewer = apiReviewer();
    $case     = apiSubmittedCase();
    $case->update([
        'assigned_reviewer' => $reviewer->id,
        'status'            => CaseStatus::SUBMITTED->value,
    ]);

    $this->actingAs($reviewer)
        ->postJson("/api/credentialing/cases/{$case->id}/start-review")
        ->assertOk()
        ->assertJsonPath('status', CaseStatus::INITIAL_REVIEW->value);
});

it('POST /api/credentialing/cases/{case}/start-review returns 403 for plain member', function () {
    $member = User::factory()->create();
    UserProfile::create(['user_id' => $member->id, 'first_name' => 'Plain', 'last_name' => 'Member']);
    $member->addRole(UserRole::MEMBER);

    $reviewer = apiReviewer();
    $case     = apiSubmittedCase();
    $case->update(['assigned_reviewer' => $reviewer->id]);

    $this->actingAs($member->fresh())
        ->postJson("/api/credentialing/cases/{$case->id}/start-review")
        ->assertForbidden();
});

it('POST /api/credentialing/cases/{case}/start-review returns 422 when case is not in SUBMITTED status', function () {
    $reviewer = apiReviewer();
    // Case is already in INITIAL_REVIEW — cannot start review again
    $case = CredentialingCase::factory()->inReview()->create([
        'assigned_reviewer' => $reviewer->id,
    ]);

    $this->actingAs($reviewer)
        ->postJson("/api/credentialing/cases/{$case->id}/start-review")
        ->assertStatus(422);
});

// ── POST /api/credentialing/cases/{case}/request-materials ────────────────────

it('POST /api/credentialing/cases/{case}/request-materials reviewer requests materials with valid notes', function () {
    $reviewer = apiReviewer();
    $case     = CredentialingCase::factory()->inReview()->create([
        'assigned_reviewer' => $reviewer->id,
    ]);

    $this->actingAs($reviewer)
        ->postJson("/api/credentialing/cases/{$case->id}/request-materials", [
            'notes' => 'Please provide updated board certification documents for the current year.',
        ])
        ->assertOk()
        ->assertJsonPath('status', CaseStatus::MORE_MATERIALS_REQUESTED->value);
});

it('POST /api/credentialing/cases/{case}/request-materials returns 422 when notes too short', function () {
    $reviewer = apiReviewer();
    $case     = CredentialingCase::factory()->inReview()->create([
        'assigned_reviewer' => $reviewer->id,
    ]);

    $this->actingAs($reviewer)
        ->postJson("/api/credentialing/cases/{$case->id}/request-materials", [
            'notes' => 'Too short',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('notes');
});

it('POST /api/credentialing/cases/{case}/request-materials returns 403 for plain member', function () {
    $member = User::factory()->create();
    UserProfile::create(['user_id' => $member->id, 'first_name' => 'Plain', 'last_name' => 'Member']);
    $member->addRole(UserRole::MEMBER);

    $reviewer = apiReviewer();
    $case     = CredentialingCase::factory()->inReview()->create([
        'assigned_reviewer' => $reviewer->id,
    ]);

    $this->actingAs($member->fresh())
        ->postJson("/api/credentialing/cases/{$case->id}/request-materials", [
            'notes' => 'Please send more documentation for verification purposes.',
        ])
        ->assertForbidden();
});
