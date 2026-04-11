<?php

namespace App\Services;

use App\Enums\CaseAction;
use App\Enums\CaseStatus;
use App\Enums\CredentialingStatus;
use App\Models\CredentialingCase;
use App\Models\Doctor;
use App\Models\User;
use App\Services\AuditService;
use App\Services\IdempotencyStore;
use Illuminate\Support\Str;
use RuntimeException;

class CredentialingService
{
    // ── Submit ────────────────────────────────────────────────────────────────

    /**
     * CRED-01: Requires LICENSE + BOARD_CERTIFICATION.
     * questions.md 2.1: Only one active case per doctor.
     *
     * Participates in the universal service-layer idempotency contract
     * (`docs/design.md:70-73`, audit Issue 3). The natural-key guard
     * (`activeCase() !== null`) still protects against logically-duplicate
     * submissions, but an explicit `$idempotencyKey` makes retries of the
     * *same* submission deterministic: they return the case that was already
     * created instead of 422-ing on the natural-key guard.
     */
    public function submitCase(Doctor $doctor, User $actor, string $idempotencyKey): CredentialingCase
    {
        $existingByKey = CredentialingCase::where('idempotency_key', $idempotencyKey)->first();
        if ($existingByKey) {
            return $existingByKey;
        }

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
            'doctor_id'       => $doctor->id,
            'status'          => CaseStatus::SUBMITTED->value,
            'submitted_at'    => now(),
            'version'         => 1,
            'idempotency_key' => $idempotencyKey,
        ]);

        $this->recordAction($case, CaseAction::SUBMIT, $actor);

        $doctor->transitionCredentialingStatus(CredentialingStatus::UNDER_REVIEW);

        AuditService::record('credentialing.case_submitted', 'CredentialingCase', $case->id, null, [
            'doctor_id' => $doctor->id,
        ]);

        return $case->fresh();
    }

    // ── Assign reviewer ───────────────────────────────────────────────────────

    /**
     * CRED-04: Case must be assigned before review starts.
     *
     * `$idempotencyKey` is REQUIRED (FIN audit Issue 4) — assignment is a
     * terminal state change (SUBMITTED cases can only be assigned once),
     * so a double-click must collapse to a no-op instead of 422'ing on the
     * second attempt.
     */
    public function assignReviewer(CredentialingCase $case, User $reviewer, User $actor, string $idempotencyKey): void
    {
        $store = new IdempotencyStore();
        if ($store->alreadyProcessed($idempotencyKey, 'credentialing.assign_reviewer', $case->id)) {
            return;
        }

        if ($case->status !== CaseStatus::SUBMITTED) {
            throw new RuntimeException('Reviewer can only be assigned on SUBMITTED cases.', 422);
        }

        $case->forceFill(['assigned_reviewer' => $reviewer->id])->save();

        $this->recordAction($case, CaseAction::ASSIGN, $actor, "Assigned to {$reviewer->username}");

        $store->record($idempotencyKey, 'credentialing.assign_reviewer', 'CredentialingCase', $case->id);
    }

    // ── Start review ──────────────────────────────────────────────────────────

    /**
     * `$idempotencyKey` is REQUIRED (FIN audit Issue 4).
     */
    public function startReview(CredentialingCase $case, User $actor, string $idempotencyKey): void
    {
        $store = new IdempotencyStore();
        if ($store->alreadyProcessed($idempotencyKey, 'credentialing.start_review', $case->id)) {
            return;
        }

        $this->assertTransition($case, CaseStatus::INITIAL_REVIEW);

        if ($case->assigned_reviewer === null) {
            throw new RuntimeException('A reviewer must be assigned before review can start.', 422);
        }

        $this->assertActorCanAct($case, $actor);

        $case->status = CaseStatus::INITIAL_REVIEW;
        $case->saveWithLock();

        $this->recordAction($case, CaseAction::START_REVIEW, $actor);

        $store->record($idempotencyKey, 'credentialing.start_review', 'CredentialingCase', $case->id);
    }

    // ── Request materials ─────────────────────────────────────────────────────

    /**
     * CRED-05: Returns case to doctor with notes; doctor must upload and resubmit.
     *
     * `$idempotencyKey` is REQUIRED (FIN audit Issue 4).
     */
    public function requestMaterials(CredentialingCase $case, User $actor, string $notes, string $idempotencyKey): void
    {
        $store = new IdempotencyStore();
        if ($store->alreadyProcessed($idempotencyKey, 'credentialing.request_materials', $case->id)) {
            return;
        }

        $this->assertTransition($case, CaseStatus::MORE_MATERIALS_REQUESTED);
        $this->assertActorCanAct($case, $actor);

        $case->status = CaseStatus::MORE_MATERIALS_REQUESTED;
        $case->saveWithLock();

        $this->recordAction($case, CaseAction::REQUEST_MATERIALS, $actor, $notes);

        // Sync doctor status
        $case->doctor->transitionCredentialingStatus(CredentialingStatus::MORE_MATERIALS_REQUESTED);

        $store->record($idempotencyKey, 'credentialing.request_materials', 'CredentialingCase', $case->id);
    }

    // ── Receive materials ─────────────────────────────────────────────────────

    /**
     * Called after doctor uploads new docs and clicks "Resubmit".
     * Transitions case → RE_REVIEW, doctor → UNDER_REVIEW.
     *
     * `$idempotencyKey` is REQUIRED (FIN audit Issue 4).
     */
    public function receiveMaterials(CredentialingCase $case, User $actor, string $idempotencyKey): void
    {
        $store = new IdempotencyStore();
        if ($store->alreadyProcessed($idempotencyKey, 'credentialing.receive_materials', $case->id)) {
            return;
        }

        $this->assertTransition($case, CaseStatus::RE_REVIEW);

        $case->status = CaseStatus::RE_REVIEW;
        $case->saveWithLock();

        $this->recordAction($case, CaseAction::RECEIVE_MATERIALS, $actor);

        $case->doctor->transitionCredentialingStatus(CredentialingStatus::UNDER_REVIEW);

        $store->record($idempotencyKey, 'credentialing.receive_materials', 'CredentialingCase', $case->id);
    }

    // ── Approve ───────────────────────────────────────────────────────────────

    /**
     * CRED-06: Case → APPROVED, doctor → APPROVED, activated_at = now.
     *
     * `$idempotencyKey` is REQUIRED (FIN audit Issue 4).
     */
    public function approve(CredentialingCase $case, User $actor, string $idempotencyKey): void
    {
        $store = new IdempotencyStore();
        if ($store->alreadyProcessed($idempotencyKey, 'credentialing.approve', $case->id)) {
            return;
        }

        $this->assertTransition($case, CaseStatus::APPROVED);
        $this->assertActorCanAct($case, $actor);

        $case->status      = CaseStatus::APPROVED;
        $case->resolved_at = now();
        $case->saveWithLock();

        $this->recordAction($case, CaseAction::APPROVE, $actor);

        $case->doctor->transitionCredentialingStatus(CredentialingStatus::APPROVED);

        $store->record($idempotencyKey, 'credentialing.approve', 'CredentialingCase', $case->id);
    }

    // ── Reject ────────────────────────────────────────────────────────────────

    /**
     * CRED-07: Terminal for this case. Doctor can open a new one.
     *
     * `$idempotencyKey` is REQUIRED (FIN audit Issue 4).
     */
    public function reject(CredentialingCase $case, User $actor, string $notes, string $idempotencyKey): void
    {
        $store = new IdempotencyStore();
        if ($store->alreadyProcessed($idempotencyKey, 'credentialing.reject', $case->id)) {
            return;
        }

        $this->assertTransition($case, CaseStatus::REJECTED);
        $this->assertActorCanAct($case, $actor);

        $case->status      = CaseStatus::REJECTED;
        $case->resolved_at = now();
        $case->saveWithLock();

        $this->recordAction($case, CaseAction::REJECT, $actor, $notes);

        $case->doctor->transitionCredentialingStatus(CredentialingStatus::REJECTED);

        $store->record($idempotencyKey, 'credentialing.reject', 'CredentialingCase', $case->id);
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

    /**
     * Object-level authorization guard: the role check in the Livewire mount
     * method only proves the actor is *a* reviewer, not the one assigned to
     * THIS case. Without this gate, any reviewer could approve/reject/
     * request-materials on a case they were not assigned to.
     *
     * Admins bypass via `CredentialingCase::canBeActedOnBy()`. State-changing
     * actions (startReview/requestMaterials/approve/reject) are the ones
     * guarded. `assignReviewer` is intentionally not guarded here because
     * assignment is an administrative step that happens *before* any reviewer
     * is attached; `receiveMaterials` is triggered by the doctor resubmitting,
     * not by a reviewer.
     */
    private function assertActorCanAct(CredentialingCase $case, User $actor): void
    {
        if (! $case->canBeActedOnBy($actor)) {
            throw new RuntimeException(
                'You are not the reviewer assigned to this case.',
                403,
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
