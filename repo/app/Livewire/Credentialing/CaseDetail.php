<?php

namespace App\Livewire\Credentialing;

use App\Enums\CaseStatus;
use App\Models\CredentialingCase;
use App\Models\User;
use App\Services\CredentialingService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app', ['title' => 'Case Detail'])]
class CaseDetail extends Component
{
    public CredentialingCase $case;

    // Action form fields
    public string $reviewerSearch = '';
    public ?string $selectedReviewerId = null;
    public string $notes = '';

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
            $service->assignReviewer($this->case, $reviewer, auth()->user());
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
            $service->startReview($this->case, auth()->user());
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
            $service->requestMaterials($this->case, auth()->user(), $this->notes);
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
            $service->approve($this->case, auth()->user());
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
            $service->reject($this->case, auth()->user(), $this->notes);
        } catch (\RuntimeException $e) {
            $this->addError('action', $e->getMessage());
            return;
        }

        $this->reset('notes');
        $this->case = $this->case->fresh(['doctor.user.profile', 'doctor.documents', 'reviewer', 'actions.actor']);
        session()->flash('success', 'Case rejected.');
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
        ]);
    }
}
