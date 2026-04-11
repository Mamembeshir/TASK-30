<?php

namespace App\Livewire\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\User;
use App\Services\AuditService;
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
        $newStatus = UserStatus::from($statusValue);

        try {
            $this->user->transitionStatus($newStatus);
        } catch (InvalidStatusTransitionException $e) {
            $this->addError('status', $e->getMessage());
            return;
        }

        $this->user = $this->user->fresh();
        session()->flash('success', "Status changed to {$newStatus->label()}.");
    }

    public function unlock(): void
    {
        $this->user->forceFill([
            'failed_login_count' => 0,
            'locked_until'       => null,
        ])->save();

        AuditService::record('user.unlocked', 'User', $this->user->id, null, null);

        $this->user = $this->user->fresh();
        session()->flash('success', 'Account unlocked.');
    }

    // ── Role management ───────────────────────────────────────────────────────

    public function saveRoles(): void
    {
        $allRoles = UserRole::cases();

        foreach ($allRoles as $role) {
            $shouldHave = ! empty($this->selectedRoles[$role->value]);
            $hasNow     = $this->user->hasRole($role);

            if ($shouldHave && ! $hasNow) {
                $this->user->addRole($role);
            } elseif (! $shouldHave && $hasNow) {
                $this->user->removeRole($role);
            }
        }

        $this->user->load('roles');
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
