<?php

namespace App\Livewire\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app', ['title' => 'User Management'])]
class UserList extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $filterStatus = '';

    #[Url]
    public string $filterRole = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatedFilterRole(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $query = User::with(['profile', 'roles'])
            ->when($this->search, fn ($q) =>
                $q->where(fn ($q2) =>
                    $q2->where('username', 'ilike', "%{$this->search}%")
                       ->orWhere('email', 'ilike', "%{$this->search}%")
                       ->orWhereHas('profile', fn ($p) =>
                           $p->where('first_name', 'ilike', "%{$this->search}%")
                             ->orWhere('last_name', 'ilike', "%{$this->search}%")
                       )
                )
            )
            ->when($this->filterStatus, fn ($q) =>
                $q->where('status', $this->filterStatus)
            )
            ->when($this->filterRole, fn ($q) =>
                $q->whereHas('roles', fn ($r) =>
                    $r->where('role', $this->filterRole)
                )
            )
            ->orderBy('created_at', 'desc');

        return view('livewire.admin.user-list', [
            'users'    => $query->paginate(25),
            'statuses' => UserStatus::cases(),
            'roles'    => UserRole::cases(),
        ]);
    }
}
