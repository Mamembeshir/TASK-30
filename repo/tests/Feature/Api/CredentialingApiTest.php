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

// ── POST /api/credentialing/doctors/{doctor}/upload-document ──────────────────

it('POST /api/credentialing/doctors/{doctor}/upload-document allows doctor to upload their own document (201)', function () {
    $doctor = \App\Models\Doctor::factory()->create();
    $user   = $doctor->user;

    $this->actingAs($user)
        ->postJson("/api/credentialing/doctors/{$doctor->id}/upload-document", [
            'document_type' => DocumentType::LICENSE->value,
            'file'          => \Illuminate\Http\UploadedFile::fake()->create('license.pdf', 512, 'application/pdf'),
        ])
        ->assertCreated()
        ->assertJsonPath('document_type', DocumentType::LICENSE->value);
});

it('POST /api/credentialing/doctors/{doctor}/upload-document returns 403 when caller is not the doctor', function () {
    $doctor    = \App\Models\Doctor::factory()->create();
    $otherUser = User::factory()->create();
    UserProfile::create(['user_id' => $otherUser->id, 'first_name' => 'Other', 'last_name' => 'User']);
    $otherUser->addRole(UserRole::MEMBER);

    $this->actingAs($otherUser->fresh())
        ->postJson("/api/credentialing/doctors/{$doctor->id}/upload-document", [
            'document_type' => DocumentType::LICENSE->value,
            'file'          => \Illuminate\Http\UploadedFile::fake()->create('license.pdf', 512, 'application/pdf'),
        ])
        ->assertForbidden();
});

it('POST /api/credentialing/doctors/{doctor}/upload-document returns 422 when file is missing', function () {
    $doctor = \App\Models\Doctor::factory()->create();
    $user   = $doctor->user;

    $this->actingAs($user)
        ->postJson("/api/credentialing/doctors/{$doctor->id}/upload-document", [
            'document_type' => DocumentType::LICENSE->value,
            // no 'file' key
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('file');
});

it('POST /api/credentialing/doctors/{doctor}/upload-document returns 422 when document_type is missing', function () {
    $doctor = \App\Models\Doctor::factory()->create();
    $user   = $doctor->user;

    $this->actingAs($user)
        ->postJson("/api/credentialing/doctors/{$doctor->id}/upload-document", [
            'file' => \Illuminate\Http\UploadedFile::fake()->create('license.pdf', 512, 'application/pdf'),
            // no 'document_type' key
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('document_type');
});

// ── POST /api/credentialing/doctors/{doctor}/submit-case ──────────────────────

it('POST /api/credentialing/doctors/{doctor}/submit-case creates a SUBMITTED case (201)', function () {
    $doctor = \App\Models\Doctor::factory()->create();
    $user   = $doctor->user;

    // Doctor needs the two required document types before submitting
    DoctorDocument::factory()->create(['doctor_id' => $doctor->id, 'document_type' => DocumentType::LICENSE]);
    DoctorDocument::factory()->create(['doctor_id' => $doctor->id, 'document_type' => DocumentType::BOARD_CERTIFICATION]);

    $this->actingAs($user)
        ->postJson("/api/credentialing/doctors/{$doctor->id}/submit-case")
        ->assertCreated()
        ->assertJsonPath('status', CaseStatus::SUBMITTED->value);
});

it('POST /api/credentialing/doctors/{doctor}/submit-case is idempotent on the same key', function () {
    $doctor = \App\Models\Doctor::factory()->create();
    $user   = $doctor->user;
    DoctorDocument::factory()->create(['doctor_id' => $doctor->id, 'document_type' => DocumentType::LICENSE]);
    DoctorDocument::factory()->create(['doctor_id' => $doctor->id, 'document_type' => DocumentType::BOARD_CERTIFICATION]);
    $key = (string) \Illuminate\Support\Str::uuid();

    $r1 = $this->actingAs($user)
        ->postJson("/api/credentialing/doctors/{$doctor->id}/submit-case", ['idempotency_key' => $key])
        ->assertCreated();

    $r2 = $this->actingAs($user)
        ->postJson("/api/credentialing/doctors/{$doctor->id}/submit-case", ['idempotency_key' => $key])
        ->assertCreated();

    expect($r1->json('id'))->toBe($r2->json('id'));
});

it('POST /api/credentialing/doctors/{doctor}/submit-case returns 403 when caller is not the doctor', function () {
    $doctor    = \App\Models\Doctor::factory()->create();
    $otherUser = User::factory()->create();
    UserProfile::create(['user_id' => $otherUser->id, 'first_name' => 'Other', 'last_name' => 'User']);
    $otherUser->addRole(UserRole::MEMBER);

    $this->actingAs($otherUser->fresh())
        ->postJson("/api/credentialing/doctors/{$doctor->id}/submit-case")
        ->assertForbidden();
});

it('POST /api/credentialing/doctors/{doctor}/submit-case returns 422 when required documents are missing', function () {
    $doctor = \App\Models\Doctor::factory()->create();
    $user   = $doctor->user;
    // No documents uploaded — missing LICENSE + BOARD_CERTIFICATION

    $this->actingAs($user)
        ->postJson("/api/credentialing/doctors/{$doctor->id}/submit-case")
        ->assertStatus(422);
});

// ── POST /api/credentialing/doctors/{doctor}/resubmit-case ───────────────────

it('POST /api/credentialing/doctors/{doctor}/resubmit-case transitions case to RE_REVIEW (200)', function () {
    $reviewer = apiReviewer();
    $doctor   = \App\Models\Doctor::factory()->create();
    $user     = $doctor->user;

    $case = CredentialingCase::factory()->moreMaterialsRequested()->create([
        'doctor_id'         => $doctor->id,
        'assigned_reviewer' => $reviewer->id,
    ]);

    $this->actingAs($user)
        ->postJson("/api/credentialing/doctors/{$doctor->id}/resubmit-case")
        ->assertOk()
        ->assertJsonPath('status', CaseStatus::RE_REVIEW->value);
});

it('POST /api/credentialing/doctors/{doctor}/resubmit-case returns 403 when caller is not the doctor', function () {
    $reviewer  = apiReviewer();
    $doctor    = \App\Models\Doctor::factory()->create();
    $otherUser = User::factory()->create();
    UserProfile::create(['user_id' => $otherUser->id, 'first_name' => 'Other', 'last_name' => 'User']);
    $otherUser->addRole(UserRole::MEMBER);

    CredentialingCase::factory()->moreMaterialsRequested()->create([
        'doctor_id'         => $doctor->id,
        'assigned_reviewer' => $reviewer->id,
    ]);

    $this->actingAs($otherUser->fresh())
        ->postJson("/api/credentialing/doctors/{$doctor->id}/resubmit-case")
        ->assertForbidden();
});

it('POST /api/credentialing/doctors/{doctor}/resubmit-case returns 422 when no active case exists', function () {
    $doctor = \App\Models\Doctor::factory()->create();
    $user   = $doctor->user;
    // No case created for this doctor

    $this->actingAs($user)
        ->postJson("/api/credentialing/doctors/{$doctor->id}/resubmit-case")
        ->assertStatus(422);
});

it('POST /api/credentialing/doctors/{doctor}/resubmit-case returns 422 when active case is not in MORE_MATERIALS_REQUESTED state', function () {
    $reviewer = apiReviewer();
    $doctor   = \App\Models\Doctor::factory()->create();
    $user     = $doctor->user;

    // Case is in INITIAL_REVIEW — cannot resubmit from here
    CredentialingCase::factory()->inReview()->create([
        'doctor_id'         => $doctor->id,
        'assigned_reviewer' => $reviewer->id,
    ]);

    $this->actingAs($user)
        ->postJson("/api/credentialing/doctors/{$doctor->id}/resubmit-case")
        ->assertStatus(422);
});

// ── POST /api/credentialing/cases/{case}/upload-document ─────────────────────

it('POST /api/credentialing/cases/{case}/upload-document allows assigned reviewer to upload a document (201)', function () {
    $reviewer = apiReviewer();
    $case     = CredentialingCase::factory()->inReview()->create([
        'assigned_reviewer' => $reviewer->id,
    ]);

    $this->actingAs($reviewer)
        ->postJson("/api/credentialing/cases/{$case->id}/upload-document", [
            'document_type' => DocumentType::BOARD_CERTIFICATION->value,
            'file'          => \Illuminate\Http\UploadedFile::fake()->create('board_cert.pdf', 1024, 'application/pdf'),
        ])
        ->assertCreated()
        ->assertJsonPath('document_type', DocumentType::BOARD_CERTIFICATION->value);
});

it('POST /api/credentialing/cases/{case}/upload-document returns 403 when caller is not assigned to the doctor', function () {
    $assigned   = apiReviewer();
    $unassigned = apiReviewer();
    $case       = CredentialingCase::factory()->inReview()->create([
        'assigned_reviewer' => $assigned->id,
    ]);

    $this->actingAs($unassigned)
        ->postJson("/api/credentialing/cases/{$case->id}/upload-document", [
            'document_type' => DocumentType::LICENSE->value,
            'file'          => \Illuminate\Http\UploadedFile::fake()->create('license.pdf', 512, 'application/pdf'),
        ])
        ->assertForbidden();
});

it('POST /api/credentialing/cases/{case}/upload-document returns 422 when file is missing', function () {
    $reviewer = apiReviewer();
    $case     = CredentialingCase::factory()->inReview()->create([
        'assigned_reviewer' => $reviewer->id,
    ]);

    $this->actingAs($reviewer)
        ->postJson("/api/credentialing/cases/{$case->id}/upload-document", [
            'document_type' => DocumentType::LICENSE->value,
            // no 'file' key
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('file');
});

it('POST /api/credentialing/cases/{case}/upload-document returns 403 for plain member', function () {
    $member = User::factory()->create();
    UserProfile::create(['user_id' => $member->id, 'first_name' => 'Plain', 'last_name' => 'Member']);
    $member->addRole(UserRole::MEMBER);

    $reviewer = apiReviewer();
    $case     = CredentialingCase::factory()->inReview()->create([
        'assigned_reviewer' => $reviewer->id,
    ]);

    $this->actingAs($member->fresh())
        ->postJson("/api/credentialing/cases/{$case->id}/upload-document", [
            'document_type' => DocumentType::LICENSE->value,
            'file'          => \Illuminate\Http\UploadedFile::fake()->create('license.pdf', 512, 'application/pdf'),
        ])
        ->assertForbidden();
});
