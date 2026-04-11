<?php

namespace App\Livewire\Credentialing;

use App\Enums\CaseStatus;
use App\Enums\UserRole;
use App\Models\CredentialingCase;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app', ['title' => 'Credentialing Cases'])]
class CaseList extends Component
{
    use WithPagination;

    #[Url]
    public string $filterStatus = '';

    #[Url]
    public string $search = '';

    public function mount(): void
    {
        $user = auth()->user();
        if (! $user->isCredentialingReviewer() && ! $user->isAdmin()) {
            abort(403, 'Access restricted to credentialing reviewers.');
        }
    }

    public function updatedFilterStatus(): void { $this->resetPage(); }
    public function updatedSearch(): void       { $this->resetPage(); }

    public function render()
    {
        $query = CredentialingCase::with(['doctor.user.profile', 'reviewer'])
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->search, fn ($q) =>
                $q->whereHas('doctor.user', fn ($u) =>
                    $u->where('username', 'ilike', "%{$this->search}%")
                      ->orWhere('email', 'ilike', "%{$this->search}%")
                      ->orWhereHas('profile', fn ($p) =>
                          $p->where('first_name', 'ilike', "%{$this->search}%")
                            ->orWhere('last_name', 'ilike', "%{$this->search}%")
                      )
                )
            )
            ->orderBy('submitted_at', 'desc');

        return view('livewire.credentialing.case-list', [
            'cases'    => $query->paginate(20),
            'statuses' => CaseStatus::cases(),
        ]);
    }
}
