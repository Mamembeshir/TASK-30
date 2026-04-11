<?php

namespace App\Livewire\Credentialing;

use App\Enums\CaseStatus;
use App\Enums\DocumentType;
use App\Models\CredentialingCase;
use App\Models\User;
use App\Services\CredentialingService;
use App\Services\DocumentService;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app', ['title' => 'Case Detail'])]
class CaseDetail extends Component
{
    use WithFileUploads;

    public CredentialingCase $case;

    // Action form fields
    public string $reviewerSearch = '';
    public ?string $selectedReviewerId = null;
    public string $notes = '';

    // Staff document upload fields
    public $staffUploadFile = null;
    public string $staffUploadType = '';

    public function mount(CredentialingCase $case): void
    {
        $user = auth()->user();
        if (! $user->isCredentialingReviewer() && ! $user->isAdmin()) {
            abort(403, 'Access restricted to credentialing reviewers.');
        }

        $this->case = $case->load(['doctor.user.profile', 'doctor.documents', 'reviewer', 'actions.actor']);
    }

    // ── Actions ───────────────────────────────────────────────────────────────

    public function assignReviewer(): void
    {
        $this->validate(['selectedReviewerId' => ['required', 'exists:users,id']]);

        $reviewer = User::findOrFail($this->selectedReviewerId);
        $service  = new CredentialingService();

        try {
            // Deterministic per-(case, reviewer) key so that a double-click
            // trying to assign the same reviewer converges on a no-op. A
            // *different* reviewer gets a different key, which is correct —
            // the second assignment would fail the SUBMITTED guard anyway.
            $service->assignReviewer(
                $this->case,
                $reviewer,
                auth()->user(),
                'credentialing.assign_reviewer.' . $this->case->id . '.' . $reviewer->id,
            );
        } catch (\RuntimeException $e) {
            $this->addError('action', $e->getMessage());
            return;
        }

        $this->case = $this->case->fresh(['doctor.user.profile', 'doctor.documents', 'reviewer', 'actions.actor']);
        $this->reset('selectedReviewerId', 'reviewerSearch');
        session()->flash('success', 'Reviewer assigned.');
    }

    public function startReview(): void
    {
        $service = new CredentialingService();

        try {
            $service->startReview(
                $this->case,
                auth()->user(),
                'credentialing.start_review.' . $this->case->id,
            );
        } catch (\RuntimeException $e) {
            $this->addError('action', $e->getMessage());
            return;
        }

        $this->case = $this->case->fresh(['doctor.user.profile', 'doctor.documents', 'reviewer', 'actions.actor']);
        session()->flash('success', 'Review started.');
    }

    public function requestMaterials(): void
    {
        $this->validate(['notes' => ['required', 'string', 'min:10']]);

        $service = new CredentialingService();

        try {
            // The key includes a hash of the notes so that a second
            // request-materials action on the *same case with different
            // notes* is treated as a distinct (legitimate) request, while
            // a retry with identical notes collapses.
            $service->requestMaterials(
                $this->case,
                auth()->user(),
                $this->notes,
                'credentialing.request_materials.' . $this->case->id . '.' . substr(sha1($this->notes), 0, 12),
            );
        } catch (\RuntimeException $e) {
            $this->addError('action', $e->getMessage());
            return;
        }

        $this->reset('notes');
        $this->case = $this->case->fresh(['doctor.user.profile', 'doctor.documents', 'reviewer', 'actions.actor']);
        session()->flash('success', 'Materials requested. Doctor has been notified.');
    }

    public function approve(): void
    {
        $service = new CredentialingService();

        try {
            $service->approve(
                $this->case,
                auth()->user(),
                'credentialing.approve.' . $this->case->id,
            );
        } catch (\RuntimeException $e) {
            $this->addError('action', $e->getMessage());
            return;
        }

        $this->case = $this->case->fresh(['doctor.user.profile', 'doctor.documents', 'reviewer', 'actions.actor']);
        session()->flash('success', 'Doctor approved. They may now lead trips.');
    }

    public function reject(): void
    {
        $this->validate(['notes' => ['required', 'string', 'min:10']]);

        $service = new CredentialingService();

        try {
            $service->reject(
                $this->case,
                auth()->user(),
                $this->notes,
                'credentialing.reject.' . $this->case->id . '.' . substr(sha1($this->notes), 0, 12),
            );
        } catch (\RuntimeException $e) {
            $this->addError('action', $e->getMessage());
            return;
        }

        $this->reset('notes');
        $this->case = $this->case->fresh(['doctor.user.profile', 'doctor.documents', 'reviewer', 'actions.actor']);
        session()->flash('success', 'Case rejected.');
    }

    public function uploadDocument(): void
    {
        $this->validate([
            'staffUploadFile' => ['required', 'file', 'max:10240'],
            'staffUploadType' => ['required', 'in:' . implode(',', array_column(DocumentType::cases(), 'value'))],
        ]);

        $actor          = auth()->user();
        $documentService = new DocumentService();

        // Object-level authorization: admin or the assigned reviewer on this
        // case's doctor may upload. Enforced here so the HTTP layer (route)
        // only needs role-level middleware — fine-grained object access is
        // handled at the service boundary.
        if (! $documentService->canUploadFor($this->case->doctor, $actor)) {
            $this->addError('staffUploadFile', 'You are not authorised to upload documents for this doctor.');
            return;
        }

        try {
            $documentService->upload(
                $this->case->doctor,
                $this->staffUploadFile,
                DocumentType::from($this->staffUploadType),
                $actor,
            );
        } catch (\RuntimeException $e) {
            $this->addError('staffUploadFile', $e->getMessage());
            return;
        }

        $this->reset('staffUploadFile', 'staffUploadType');
        $this->case = $this->case->fresh(['doctor.user.profile', 'doctor.documents', 'reviewer', 'actions.actor']);
        session()->flash('success', 'Document uploaded.');
    }

    public function render()
    {
        $reviewers = collect();
        if ($this->reviewerSearch) {
            $reviewers = User::whereHas('roles', fn ($q) => $q->where('role', 'CREDENTIALING_REVIEWER'))
                ->where(fn ($q) =>
                    $q->where('username', 'ilike', "%{$this->reviewerSearch}%")
                      ->orWhereHas('profile', fn ($p) =>
                          $p->where('first_name', 'ilike', "%{$this->reviewerSearch}%")
                            ->orWhere('last_name', 'ilike', "%{$this->reviewerSearch}%")
                      )
                )
                ->limit(10)
                ->get();
        }

        return view('livewire.credentialing.case-detail', [
            'allowedTransitions' => $this->case->status->allowedTransitions(),
            'reviewers'          => $reviewers,
            'documentTypes'      => DocumentType::cases(),
            'canUpload'          => (new DocumentService())->canUploadFor($this->case->doctor, auth()->user()),
        ]);
    }
}
