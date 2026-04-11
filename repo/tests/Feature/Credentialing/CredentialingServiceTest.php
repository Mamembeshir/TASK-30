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
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * Helper: each call to `submitCase` in the tests below needs a unique
 * idempotency key to satisfy the contract introduced for audit Issue 3.
 * Wrapping it keeps the per-test call sites readable — the tests care about
 * submission semantics, not about generating UUIDs.
 */
function credKey(): string
{
    return (string) Str::uuid();
}

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

    $case = $svc->submitCase($doctor, $actor, credKey());

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

    expect(fn () => $svc->submitCase($doctor, User::factory()->create(), credKey()))
        ->toThrow(\RuntimeException::class);
});

it('rejects submit without BOARD_CERTIFICATION document → 422', function () {
    $doctor = Doctor::factory()->create();
    DoctorDocument::factory()->create([
        'doctor_id'     => $doctor->id,
        'document_type' => DocumentType::LICENSE->value,
    ]);

    $svc = new CredentialingService();

    expect(fn () => $svc->submitCase($doctor, User::factory()->create(), credKey()))
        ->toThrow(\RuntimeException::class);
});

it('rejects a second case while one is active (questions.md 2.1)', function () {
    $doctor = makeDoctorWithRequiredDocs();
    $actor  = User::factory()->create();
    $svc    = new CredentialingService();

    $svc->submitCase($doctor, $actor, credKey());

    // Different idempotency key → natural-key guard trips.
    expect(fn () => $svc->submitCase($doctor->fresh(), $actor, credKey()))
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

    $case = $svc->submitCase($doctor, $actor, credKey());

    expect($case->status)->toBe(CaseStatus::SUBMITTED);
});

// ── Full happy path ───────────────────────────────────────────────────────────

it('full happy path: submit → assign → review → approve → doctor APPROVED', function () {
    $doctor   = makeDoctorWithRequiredDocs();
    $actor    = User::factory()->create();
    $reviewer = makeReviewer();
    $svc      = new CredentialingService();

    $case = $svc->submitCase($doctor, $actor, credKey());
    expect($case->status)->toBe(CaseStatus::SUBMITTED);

    $svc->assignReviewer($case, $reviewer, $actor, credKey());
    $case = $case->fresh();
    expect($case->assigned_reviewer)->toBe($reviewer->id);

    $svc->startReview($case, $reviewer, credKey());
    $case = $case->fresh();
    expect($case->status)->toBe(CaseStatus::INITIAL_REVIEW);

    $svc->approve($case, $reviewer, credKey());
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

    $case = $svc->submitCase($doctor, $actor, credKey());
    $svc->assignReviewer($case, $reviewer, $actor, credKey());
    $case = $case->fresh();
    $svc->startReview($case, $reviewer, credKey());
    $case = $case->fresh();

    $svc->requestMaterials($case, $reviewer, 'Please upload a current license.', credKey());
    $case = $case->fresh();
    expect($case->status)->toBe(CaseStatus::MORE_MATERIALS_REQUESTED);
    expect($doctor->fresh()->credentialing_status)->toBe(CredentialingStatus::MORE_MATERIALS_REQUESTED);

    $svc->receiveMaterials($case, $actor, credKey());
    $case = $case->fresh();
    expect($case->status)->toBe(CaseStatus::RE_REVIEW);
    expect($doctor->fresh()->credentialing_status)->toBe(CredentialingStatus::UNDER_REVIEW);

    $svc->approve($case, $reviewer, credKey());
    expect($case->fresh()->status)->toBe(CaseStatus::APPROVED);
    expect($doctor->fresh()->credentialing_status)->toBe(CredentialingStatus::APPROVED);
});

// ── Reject ────────────────────────────────────────────────────────────────────

it('reject case → doctor REJECTED → case is terminal', function () {
    $doctor   = makeDoctorWithRequiredDocs();
    $actor    = User::factory()->create();
    $reviewer = makeReviewer();
    $svc      = new CredentialingService();

    $case = $svc->submitCase($doctor, $actor, credKey());
    $svc->assignReviewer($case, $reviewer, $actor, credKey());
    $case = $case->fresh();
    $svc->startReview($case, $reviewer, credKey());
    $case = $case->fresh();
    $svc->reject($case, $reviewer, 'Documentation insufficient.', credKey());

    $case = $case->fresh();
    expect($case->status)->toBe(CaseStatus::REJECTED);
    expect($case->status->isTerminal())->toBeTrue();
    expect($doctor->fresh()->credentialing_status)->toBe(CredentialingStatus::REJECTED);
});

// ── Object-level authorization (Audit Issue 2) ───────────────────────────────
//
// Regression for the "credentialing reviewer can act on ANY case" finding.
// The Livewire mount only checks `isCredentialingReviewer()`, so without a
// service-layer check any reviewer could approve/reject/request-materials on
// a case assigned to a different reviewer. The fix enforces
// `$case->canBeActedOnBy($actor)` inside the state-transition methods.

it('startReview is rejected when actor is not the assigned reviewer', function () {
    $doctor     = makeDoctorWithRequiredDocs();
    $admin      = User::factory()->create();
    $assigned   = makeReviewer();
    $unassigned = makeReviewer(); // different reviewer, same role
    $svc        = new CredentialingService();

    $case = $svc->submitCase($doctor, $admin, credKey());
    $svc->assignReviewer($case, $assigned, $admin, credKey());
    $case = $case->fresh();

    expect(fn () => $svc->startReview($case, $unassigned, credKey()))
        ->toThrow(\RuntimeException::class, 'not the reviewer assigned');

    // Case must not have transitioned.
    expect($case->fresh()->status)->toBe(CaseStatus::SUBMITTED);
});

it('approve is rejected when actor is not the assigned reviewer', function () {
    $doctor     = makeDoctorWithRequiredDocs();
    $admin      = User::factory()->create();
    $assigned   = makeReviewer();
    $unassigned = makeReviewer();
    $svc        = new CredentialingService();

    $case = $svc->submitCase($doctor, $admin, credKey());
    $svc->assignReviewer($case, $assigned, $admin, credKey());
    $svc->startReview($case->fresh(), $assigned, credKey());
    $case = $case->fresh();

    expect(fn () => $svc->approve($case, $unassigned, credKey()))
        ->toThrow(\RuntimeException::class, 'not the reviewer assigned');

    expect($case->fresh()->status)->toBe(CaseStatus::INITIAL_REVIEW);
    expect($doctor->fresh()->credentialing_status)->toBe(CredentialingStatus::UNDER_REVIEW);
});

it('reject is rejected when actor is not the assigned reviewer', function () {
    $doctor     = makeDoctorWithRequiredDocs();
    $admin      = User::factory()->create();
    $assigned   = makeReviewer();
    $unassigned = makeReviewer();
    $svc        = new CredentialingService();

    $case = $svc->submitCase($doctor, $admin, credKey());
    $svc->assignReviewer($case, $assigned, $admin, credKey());
    $svc->startReview($case->fresh(), $assigned, credKey());
    $case = $case->fresh();

    expect(fn () => $svc->reject($case, $unassigned, 'Documents unclear.', credKey()))
        ->toThrow(\RuntimeException::class, 'not the reviewer assigned');

    expect($case->fresh()->status)->toBe(CaseStatus::INITIAL_REVIEW);
});

it('requestMaterials is rejected when actor is not the assigned reviewer', function () {
    $doctor     = makeDoctorWithRequiredDocs();
    $admin      = User::factory()->create();
    $assigned   = makeReviewer();
    $unassigned = makeReviewer();
    $svc        = new CredentialingService();

    $case = $svc->submitCase($doctor, $admin, credKey());
    $svc->assignReviewer($case, $assigned, $admin, credKey());
    $svc->startReview($case->fresh(), $assigned, credKey());
    $case = $case->fresh();

    expect(fn () => $svc->requestMaterials($case, $unassigned, 'Please upload a clearer license.', credKey()))
        ->toThrow(\RuntimeException::class, 'not the reviewer assigned');

    expect($case->fresh()->status)->toBe(CaseStatus::INITIAL_REVIEW);
});

it('admin can act on any case regardless of assignment', function () {
    $doctor   = makeDoctorWithRequiredDocs();
    $actor    = User::factory()->create();
    $assigned = makeReviewer();
    $admin    = User::factory()->create();
    $admin->roles()->create(['role' => UserRole::ADMIN->value, 'assigned_at' => now()]);
    $admin = $admin->fresh();

    $svc  = new CredentialingService();
    $case = $svc->submitCase($doctor, $actor, credKey());
    $svc->assignReviewer($case, $assigned, $actor, credKey());
    $svc->startReview($case->fresh(), $assigned, credKey());

    // Admin is not the assigned reviewer but canBeActedOnBy() returns true for admins.
    $svc->approve($case->fresh(), $admin, credKey());

    expect($case->fresh()->status)->toBe(CaseStatus::APPROVED);
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
