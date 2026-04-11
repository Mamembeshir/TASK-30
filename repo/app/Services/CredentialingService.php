<?php

namespace App\Services;

use App\Enums\CaseAction;
use App\Enums\CaseStatus;
use App\Enums\CredentialingStatus;
use App\Models\CredentialingCase;
use App\Models\Doctor;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Str;
use RuntimeException;

class CredentialingService
{
    // ── Submit ────────────────────────────────────────────────────────────────

    /**
     * CRED-01: Requires LICENSE + BOARD_CERTIFICATION.
     * questions.md 2.1: Only one active case per doctor.
     */
    public function submitCase(Doctor $doctor, User $actor): CredentialingCase
    {
        if (! $doctor->credentialing_status->canSubmitNewCase()) {
            throw new RuntimeException(
                'A case cannot be submitted while the doctor status is ' . $doctor->credentialing_status->label() . '.',
                422,
            );
        }

        if ($doctor->activeCase() !== null) {
            throw new RuntimeException('This doctor already has an active credentialing case.', 422);
        }

        if (! $doctor->hasRequiredDocuments()) {
            throw new RuntimeException(
                'Submission requires both a Medical License and Board Certification document.',
                422,
            );
        }

        $case = CredentialingCase::create([
            'doctor_id'    => $doctor->id,
            'status'       => CaseStatus::SUBMITTED->value,
            'submitted_at' => now(),
            'version'      => 1,
        ]);

        $this->recordAction($case, CaseAction::SUBMIT, $actor);

        $doctor->transitionCredentialingStatus(CredentialingStatus::UNDER_REVIEW);

        AuditService::record('credentialing.case_submitted', 'CredentialingCase', $case->id, null, [
            'doctor_id' => $doctor->id,
        ]);

        return $case->fresh();
    }

    // ── Assign reviewer ───────────────────────────────────────────────────────

    /** CRED-04: Case must be assigned before review starts. */
    public function assignReviewer(CredentialingCase $case, User $reviewer, User $actor): void
    {
        if ($case->status !== CaseStatus::SUBMITTED) {
            throw new RuntimeException('Reviewer can only be assigned on SUBMITTED cases.', 422);
        }

        $case->forceFill(['assigned_reviewer' => $reviewer->id])->save();

        $this->recordAction($case, CaseAction::ASSIGN, $actor, "Assigned to {$reviewer->username}");
    }

    // ── Start review ──────────────────────────────────────────────────────────

    public function startReview(CredentialingCase $case, User $actor): void
    {
        $this->assertTransition($case, CaseStatus::INITIAL_REVIEW);

        if ($case->assigned_reviewer === null) {
            throw new RuntimeException('A reviewer must be assigned before review can start.', 422);
        }

        $case->status = CaseStatus::INITIAL_REVIEW;
        $case->saveWithLock();

        $this->recordAction($case, CaseAction::START_REVIEW, $actor);
    }

    // ── Request materials ─────────────────────────────────────────────────────

    /**
     * CRED-05: Returns case to doctor with notes; doctor must upload and resubmit.
     */
    public function requestMaterials(CredentialingCase $case, User $actor, string $notes): void
    {
        $this->assertTransition($case, CaseStatus::MORE_MATERIALS_REQUESTED);

        $case->status = CaseStatus::MORE_MATERIALS_REQUESTED;
        $case->saveWithLock();

        $this->recordAction($case, CaseAction::REQUEST_MATERIALS, $actor, $notes);

        // Sync doctor status
        $case->doctor->transitionCredentialingStatus(CredentialingStatus::MORE_MATERIALS_REQUESTED);
    }

    // ── Receive materials ─────────────────────────────────────────────────────

    /**
     * Called after doctor uploads new docs and clicks "Resubmit".
     * Transitions case → RE_REVIEW, doctor → UNDER_REVIEW.
     */
    public function receiveMaterials(CredentialingCase $case, User $actor): void
    {
        $this->assertTransition($case, CaseStatus::RE_REVIEW);

        $case->status = CaseStatus::RE_REVIEW;
        $case->saveWithLock();

        $this->recordAction($case, CaseAction::RECEIVE_MATERIALS, $actor);

        $case->doctor->transitionCredentialingStatus(CredentialingStatus::UNDER_REVIEW);
    }

    // ── Approve ───────────────────────────────────────────────────────────────

    /**
     * CRED-06: Case → APPROVED, doctor → APPROVED, activated_at = now.
     */
    public function approve(CredentialingCase $case, User $actor): void
    {
        $this->assertTransition($case, CaseStatus::APPROVED);

        $case->status      = CaseStatus::APPROVED;
        $case->resolved_at = now();
        $case->saveWithLock();

        $this->recordAction($case, CaseAction::APPROVE, $actor);

        $case->doctor->transitionCredentialingStatus(CredentialingStatus::APPROVED);
    }

    // ── Reject ────────────────────────────────────────────────────────────────

    /**
     * CRED-07: Terminal for this case. Doctor can open a new one.
     */
    public function reject(CredentialingCase $case, User $actor, string $notes): void
    {
        $this->assertTransition($case, CaseStatus::REJECTED);

        $case->status      = CaseStatus::REJECTED;
        $case->resolved_at = now();
        $case->saveWithLock();

        $this->recordAction($case, CaseAction::REJECT, $actor, $notes);

        $case->doctor->transitionCredentialingStatus(CredentialingStatus::REJECTED);
    }

    // ── Internals ─────────────────────────────────────────────────────────────

    private function assertTransition(CredentialingCase $case, CaseStatus $target): void
    {
        if (! in_array($target, $case->status->allowedTransitions(), true)) {
            throw new RuntimeException(
                "Cannot transition case from {$case->status->label()} to {$target->label()}.",
                422,
            );
        }
    }

    private function recordAction(
        CredentialingCase $case,
        CaseAction        $action,
        User              $actor,
        ?string           $notes = null,
    ): void {
        $case->actions()->create([
            'action'    => $action->value,
            'actor_id'  => $actor->id,
            'notes'     => $notes,
            'timestamp' => now(),
        ]);
    }
}
