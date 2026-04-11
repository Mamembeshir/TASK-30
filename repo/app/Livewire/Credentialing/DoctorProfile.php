<?php

namespace App\Livewire\Credentialing;

use App\Enums\DocumentType;
use App\Models\Doctor;
use App\Services\CredentialingService;
use App\Services\DocumentService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app', ['title' => 'My Credentialing'])]
class DoctorProfile extends Component
{
    use WithFileUploads;

    public ?Doctor $doctor = null;

    // Upload form
    public $uploadFile      = null;
    public string $uploadType = '';

    public function mount(): void
    {
        $this->doctor = Doctor::where('user_id', Auth::id())
            ->with(['documents', 'credentialingCases' => fn ($q) => $q->latest()])
            ->first();

        if (! $this->doctor) {
            abort(403, 'No doctor profile found. Please contact an administrator.');
        }
    }

    public function uploadDocument(): void
    {
        $this->validate([
            'uploadFile' => ['required', 'file', 'max:10240'],
            'uploadType' => ['required', 'in:' . implode(',', array_column(DocumentType::cases(), 'value'))],
        ]);

        $service = new DocumentService();

        try {
            $service->upload(
                $this->doctor,
                $this->uploadFile,
                DocumentType::from($this->uploadType),
                Auth::user(),
            );
        } catch (\RuntimeException $e) {
            $this->addError('uploadFile', $e->getMessage());
            return;
        }

        $this->reset('uploadFile', 'uploadType');
        $this->doctor->load('documents');
        session()->flash('success', 'Document uploaded.');
    }

    public function submitCase(): void
    {
        $service = new CredentialingService();

        // Deterministic key: one submission attempt per (user, doctor, active
        // case slot). A double-click or network retry collapses onto the same
        // case row rather than tripping the "already has active case" guard.
        $idempotencyKey = "cred:submit:{$this->doctor->id}:{$this->doctor->credentialing_status->value}";

        try {
            $service->submitCase($this->doctor, Auth::user(), $idempotencyKey);
        } catch (\RuntimeException $e) {
            $this->addError('submit', $e->getMessage());
            return;
        }

        $this->doctor = $this->doctor->fresh(['documents', 'credentialingCases']);
        session()->flash('success', 'Case submitted for review.');
    }

    public function resubmitCase(): void
    {
        $activeCase = $this->doctor->activeCase();

        if (! $activeCase) {
            $this->addError('submit', 'No active case to resubmit.');
            return;
        }

        $service = new CredentialingService();

        try {
            $service->receiveMaterials(
                $activeCase,
                Auth::user(),
                'credentialing.receive_materials.' . $activeCase->id,
            );
        } catch (\RuntimeException $e) {
            $this->addError('submit', $e->getMessage());
            return;
        }

        $this->doctor = $this->doctor->fresh(['documents', 'credentialingCases']);
        session()->flash('success', 'Materials submitted. Case is now under re-review.');
    }

    public function render()
    {
        return view('livewire.credentialing.doctor-profile', [
            'documentTypes' => DocumentType::cases(),
            'activeCase'    => $this->doctor->activeCase(),
            'latestCase'    => $this->doctor->credentialingCases()->latest()->first(),
        ]);
    }
}
