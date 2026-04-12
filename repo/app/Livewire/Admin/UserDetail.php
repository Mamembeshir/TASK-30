<?php

namespace App\Livewire\Admin;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\ApiClient;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app', ['title' => 'User Detail'])]
class UserDetail extends Component
{
    public User $user;

    /** Role checkboxes — indexed by role value */
    public array $selectedRoles = [];

    public function mount(User $user): void
    {
        $this->user = $user->load(['profile', 'roles']);
        $this->selectedRoles = $this->user->roles->pluck('role')->flip()->map(fn () => true)->toArray();
    }

    // ── Status transitions ────────────────────────────────────────────────────

    public function transitionTo(string $statusValue): void
    {
        $response = app(ApiClient::class)->post('/admin/users/' . $this->user->id . '/transition', [
            'status' => $statusValue,
        ]);

        if ($response->status() >= 400) {
            $this->addError('status', $response->json('message') ?? 'Failed to transition status.');
            return;
        }

        $this->user = $this->user->fresh();
        session()->flash('success', "Status changed to {$statusValue}.");
    }

    public function unlock(): void
    {
        $response = app(ApiClient::class)->post('/admin/users/' . $this->user->id . '/unlock');

        if ($response->status() >= 400) {
            $this->addError('status', $response->json('message') ?? 'Failed to unlock account.');
            return;
        }

        $this->user = $this->user->fresh();
        session()->flash('success', 'Account unlocked.');
    }

    // ── Role management ───────────────────────────────────────────────────────

    public function saveRoles(): void
    {
        // Collect selected role values
        $roles = array_keys(array_filter($this->selectedRoles));

        $response = app(ApiClient::class)->put('/admin/users/' . $this->user->id . '/roles', [
            'roles' => $roles,
        ]);

        if ($response->status() >= 400) {
            $this->addError('roles', $response->json('message') ?? 'Failed to save roles.');
            return;
        }

        $this->user          = $this->user->fresh(['profile', 'roles']);
        $this->selectedRoles = $this->user->roles->pluck('role')->flip()->map(fn () => true)->toArray();
        session()->flash('success', 'Roles updated.');
    }

    public function render()
    {
        return view('livewire.admin.user-detail', [
            'allRoles'           => UserRole::cases(),
            'allowedTransitions' => $this->user->status->allowedTransitions(),
        ]);
    }
}
