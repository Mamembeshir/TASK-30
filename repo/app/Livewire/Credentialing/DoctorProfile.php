<?php

namespace App\Livewire\Credentialing;

use App\Enums\DocumentType;
use App\Models\Doctor;
use App\Services\ApiClient;
use Illuminate\Support\Facades\Auth;
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

        $response = app(ApiClient::class)->postWithFile(
            '/credentialing/doctors/' . $this->doctor->id . '/upload-document',
            ['document_type' => $this->uploadType],
            'file',
            $this->uploadFile
        );

        if ($response->status() >= 400) {
            $this->addError('uploadFile', $response->json('message') ?? 'Failed to upload document.');
            return;
        }

        $this->reset('uploadFile', 'uploadType');
        $this->doctor->load('documents');
        session()->flash('success', 'Document uploaded.');
    }

    public function submitCase(): void
    {
        $response = app(ApiClient::class)->post('/credentialing/doctors/' . $this->doctor->id . '/submit-case', [
            'idempotency_key' => "cred:submit:{$this->doctor->id}:{$this->doctor->credentialing_status->value}",
        ]);

        if ($response->status() >= 400) {
            $this->addError('submit', $response->json('message') ?? 'Failed to submit case.');
            return;
        }

        $this->doctor = $this->doctor->fresh(['documents', 'credentialingCases']);
        session()->flash('success', 'Case submitted for review.');
    }

    public function resubmitCase(): void
    {
        $response = app(ApiClient::class)->post('/credentialing/doctors/' . $this->doctor->id . '/resubmit-case');

        if ($response->status() >= 400) {
            $this->addError('submit', $response->json('message') ?? 'Failed to resubmit case.');
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
