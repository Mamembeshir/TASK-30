<?php

namespace App\Livewire\Credentialing;

use App\Enums\DocumentType;
use App\Models\CredentialingCase;
use App\Models\User;
use App\Services\ApiClient;
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

        $response = app(ApiClient::class)->post('/credentialing/cases/' . $this->case->id . '/assign', [
            'reviewer_id'     => $this->selectedReviewerId,
            'idempotency_key' => 'credentialing.assign_reviewer.' . $this->case->id . '.' . $this->selectedReviewerId,
        ]);

        if ($response->status() >= 400) {
            $this->addError('action', $response->json('message') ?? 'Failed to assign reviewer.');
            return;
        }

        $this->case = $this->case->fresh(['doctor.user.profile', 'doctor.documents', 'reviewer', 'actions.actor']);
        $this->reset('selectedReviewerId', 'reviewerSearch');
        session()->flash('success', 'Reviewer assigned.');
    }

    public function startReview(): void
    {
        $response = app(ApiClient::class)->post('/credentialing/cases/' . $this->case->id . '/start-review', [
            'idempotency_key' => 'credentialing.start_review.' . $this->case->id,
        ]);

        if ($response->status() >= 400) {
            $this->addError('action', $response->json('message') ?? 'Failed to start review.');
            return;
        }

        $this->case = $this->case->fresh(['doctor.user.profile', 'doctor.documents', 'reviewer', 'actions.actor']);
        session()->flash('success', 'Review started.');
    }

    public function requestMaterials(): void
    {
        $this->validate(['notes' => ['required', 'string', 'min:10']]);

        $response = app(ApiClient::class)->post('/credentialing/cases/' . $this->case->id . '/request-materials', [
            'notes'           => $this->notes,
            'idempotency_key' => 'credentialing.request_materials.' . $this->case->id . '.' . substr(sha1($this->notes), 0, 12),
        ]);

        if ($response->status() >= 400) {
            $this->addError('action', $response->json('message') ?? 'Failed to request materials.');
            return;
        }

        $this->reset('notes');
        $this->case = $this->case->fresh(['doctor.user.profile', 'doctor.documents', 'reviewer', 'actions.actor']);
        session()->flash('success', 'Materials requested. Doctor has been notified.');
    }

    public function approve(): void
    {
        $response = app(ApiClient::class)->post('/credentialing/cases/' . $this->case->id . '/approve', [
            'idempotency_key' => 'credentialing.approve.' . $this->case->id,
        ]);

        if ($response->status() >= 400) {
            $this->addError('action', $response->json('message') ?? 'Failed to approve.');
            return;
        }

        $this->case = $this->case->fresh(['doctor.user.profile', 'doctor.documents', 'reviewer', 'actions.actor']);
        session()->flash('success', 'Doctor approved. They may now lead trips.');
    }

    public function reject(): void
    {
        $this->validate(['notes' => ['required', 'string', 'min:10']]);

        $response = app(ApiClient::class)->post('/credentialing/cases/' . $this->case->id . '/reject', [
            'notes'           => $this->notes,
            'idempotency_key' => 'credentialing.reject.' . $this->case->id . '.' . substr(sha1($this->notes), 0, 12),
        ]);

        if ($response->status() >= 400) {
            $this->addError('action', $response->json('message') ?? 'Failed to reject.');
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

        $response = app(ApiClient::class)->postWithFile(
            '/credentialing/cases/' . $this->case->id . '/upload-document',
            ['document_type' => $this->staffUploadType],
            'file',
            $this->staffUploadFile
        );

        if ($response->status() >= 400) {
            $this->addError('staffUploadFile', $response->json('message') ?? 'Failed to upload document.');
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
