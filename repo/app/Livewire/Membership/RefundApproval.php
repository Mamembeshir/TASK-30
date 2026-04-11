<?php

namespace App\Livewire\Membership;

use App\Enums\UserRole as UserRoleEnum;
use App\Models\Refund;
use App\Services\MembershipService;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use RuntimeException;

class RefundApproval extends Component
{
    public string $error = '';

    public function mount(): void
    {
        $user = auth()->user();
        Gate::allowIf(
            $user->hasRole(UserRoleEnum::FINANCE_SPECIALIST) || $user->isAdmin()
        );
    }

    public function approve(string $id, MembershipService $service): void
    {
        $refund = Refund::findOrFail($id);

        try {
            $service->approveRefund($refund, auth()->user());
        } catch (RuntimeException $e) {
            $this->error = $e->getMessage();
            return;
        }

        $this->error = '';
    }

    public function process(string $id, MembershipService $service): void
    {
        $refund = Refund::findOrFail($id);

        try {
            $service->processRefund($refund);
        } catch (RuntimeException $e) {
            $this->error = $e->getMessage();
            return;
        }

        $this->error = '';
    }

    public function render()
    {
        $refunds = Refund::with(['payment.user', 'approver'])
            ->orderBy('created_at', 'desc')
            ->paginate(25);

        return view('livewire.membership.refund-approval', compact('refunds'))
            ->layout('layouts.app', ['title' => 'Refund Approval']);
    }
}
