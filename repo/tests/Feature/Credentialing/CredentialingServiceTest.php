<?php

use App\Enums\CaseStatus;
use App\Enums\CredentialingStatus;
use App\Enums\DocumentType;
use App\Enums\UserRole;
use App\Models\CredentialingCase;
use App\Models\Doctor;
use App\Models\DoctorDocument;
use App\Models\User;
use App\Services\CredentialingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

function makeReviewer(): User
{
    $user = User::factory()->create();
    $user->roles()->create(['role' => UserRole::CREDENTIALING_REVIEWER->value, 'assigned_at' => now()]);
    return $user;
}

function makeDoctorWithRequiredDocs(): Doctor
{
    $doctor = Doctor::factory()->create();

    DoctorDocument::factory()->create([
        'doctor_id'     => $doctor->id,
        'document_type' => DocumentType::LICENSE->value,
    ]);

    DoctorDocument::factory()->create([
        'doctor_id'     => $doctor->id,
        'document_type' => DocumentType::BOARD_CERTIFICATION->value,
    ]);

    return $doctor;
}

// ── Submit case ───────────────────────────────────────────────────────────────

it('submits a case with required docs → status SUBMITTED', function () {
    $doctor = makeDoctorWithRequiredDocs();
    $actor  = User::factory()->create();
    $svc    = new CredentialingService();

    $case = $svc->submitCase($doctor, $actor);

    expect($case->status)->toBe(CaseStatus::SUBMITTED);
    expect($doctor->fresh()->credentialing_status)->toBe(CredentialingStatus::UNDER_REVIEW);
});

it('rejects submit without LICENSE document → 422', function () {
    $doctor = Doctor::factory()->create();
    DoctorDocument::factory()->create([
        'doctor_id'     => $doctor->id,
        'document_type' => DocumentType::BOARD_CERTIFICATION->value,
    ]);

    $svc = new CredentialingService();

    expect(fn () => $svc->submitCase($doctor, User::factory()->create()))
        ->toThrow(\RuntimeException::class);
});

it('rejects submit without BOARD_CERTIFICATION document → 422', function () {
    $doctor = Doctor::factory()->create();
    DoctorDocument::factory()->create([
        'doctor_id'     => $doctor->id,
        'document_type' => DocumentType::LICENSE->value,
    ]);

    $svc = new CredentialingService();

    expect(fn () => $svc->submitCase($doctor, User::factory()->create()))
        ->toThrow(\RuntimeException::class);
});

it('rejects a second case while one is active (questions.md 2.1)', function () {
    $doctor = makeDoctorWithRequiredDocs();
    $actor  = User::factory()->create();
    $svc    = new CredentialingService();

    $svc->submitCase($doctor, $actor);

    expect(fn () => $svc->submitCase($doctor->fresh(), $actor))
        ->toThrow(\RuntimeException::class);
});

it('allows new case after REJECTED (new docs uploaded)', function () {
    $doctor = Doctor::factory()->rejected()->create();
    makeDoctorWithRequiredDocs(); // ensure doc factory creates for this doctor

    // Reset to this doctor
    $doctor = Doctor::factory()->rejected()->create();
    DoctorDocument::factory()->create(['doctor_id' => $doctor->id, 'document_type' => DocumentType::LICENSE->value]);
    DoctorDocument::factory()->create(['doctor_id' => $doctor->id, 'document_type' => DocumentType::BOARD_CERTIFICATION->value]);

    $actor = User::factory()->create();
    $svc   = new CredentialingService();

    $case = $svc->submitCase($doctor, $actor);

    expect($case->status)->toBe(CaseStatus::SUBMITTED);
});

// ── Full happy path ───────────────────────────────────────────────────────────

it('full happy path: submit → assign → review → approve → doctor APPROVED', function () {
    $doctor   = makeDoctorWithRequiredDocs();
    $actor    = User::factory()->create();
    $reviewer = makeReviewer();
    $svc      = new CredentialingService();

    $case = $svc->submitCase($doctor, $actor);
    expect($case->status)->toBe(CaseStatus::SUBMITTED);

    $svc->assignReviewer($case, $reviewer, $actor);
    $case = $case->fresh();
    expect($case->assigned_reviewer)->toBe($reviewer->id);

    $svc->startReview($case, $reviewer);
    $case = $case->fresh();
    expect($case->status)->toBe(CaseStatus::INITIAL_REVIEW);

    $svc->approve($case, $reviewer);
    $case = $case->fresh();
    expect($case->status)->toBe(CaseStatus::APPROVED);
    expect($case->doctor->fresh()->credentialing_status)->toBe(CredentialingStatus::APPROVED);
    expect($case->doctor->fresh()->activated_at)->not->toBeNull();
});

// ── Request materials → resubmit path ────────────────────────────────────────

it('request materials → doctor resubmits → re-review → approve', function () {
    $doctor   = makeDoctorWithRequiredDocs();
    $actor    = User::factory()->create();
    $reviewer = makeReviewer();
    $svc      = new CredentialingService();

    $case = $svc->submitCase($doctor, $actor);
    $svc->assignReviewer($case, $reviewer, $actor);
    $case = $case->fresh();
    $svc->startReview($case, $reviewer);
    $case = $case->fresh();

    $svc->requestMaterials($case, $reviewer, 'Please upload a current license.');
    $case = $case->fresh();
    expect($case->status)->toBe(CaseStatus::MORE_MATERIALS_REQUESTED);
    expect($doctor->fresh()->credentialing_status)->toBe(CredentialingStatus::MORE_MATERIALS_REQUESTED);

    $svc->receiveMaterials($case, $actor);
    $case = $case->fresh();
    expect($case->status)->toBe(CaseStatus::RE_REVIEW);
    expect($doctor->fresh()->credentialing_status)->toBe(CredentialingStatus::UNDER_REVIEW);

    $svc->approve($case, $reviewer);
    expect($case->fresh()->status)->toBe(CaseStatus::APPROVED);
    expect($doctor->fresh()->credentialing_status)->toBe(CredentialingStatus::APPROVED);
});

// ── Reject ────────────────────────────────────────────────────────────────────

it('reject case → doctor REJECTED → case is terminal', function () {
    $doctor   = makeDoctorWithRequiredDocs();
    $actor    = User::factory()->create();
    $reviewer = makeReviewer();
    $svc      = new CredentialingService();

    $case = $svc->submitCase($doctor, $actor);
    $svc->assignReviewer($case, $reviewer, $actor);
    $case = $case->fresh();
    $svc->startReview($case, $reviewer);
    $case = $case->fresh();
    $svc->reject($case, $reviewer, 'Documentation insufficient.');

    $case = $case->fresh();
    expect($case->status)->toBe(CaseStatus::REJECTED);
    expect($case->status->isTerminal())->toBeTrue();
    expect($doctor->fresh()->credentialing_status)->toBe(CredentialingStatus::REJECTED);
});

// ── Access control ────────────────────────────────────────────────────────────

it('reviewer-only routes return 403 for members', function () {
    $member = User::factory()->create();
    $member->roles()->create(['role' => UserRole::MEMBER->value, 'assigned_at' => now()]);

    $this->actingAs($member)
         ->get(route('credentialing.cases'))
         ->assertForbidden();
});

it('reviewer can access case list', function () {
    $reviewer = makeReviewer();

    $this->actingAs($reviewer)
         ->get(route('credentialing.cases'))
         ->assertOk();
});
