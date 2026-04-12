<?php

namespace App\Livewire\Membership;

use App\Enums\UserRole as UserRoleEnum;
use App\Models\Refund;
use App\Services\ApiClient;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

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

    public function approve(string $id): void
    {
        $refund = Refund::findOrFail($id);

        $response = app(ApiClient::class)->post('/membership/refunds/' . $refund->id . '/approve', [
            'idempotency_key' => 'refund.approve.' . $refund->id,
        ]);

        if ($response->status() >= 400) {
            $this->error = $response->json('message') ?? 'Failed to approve refund.';
            return;
        }

        $this->error = '';
    }

    public function process(string $id): void
    {
        $refund = Refund::findOrFail($id);

        $response = app(ApiClient::class)->post('/membership/refunds/' . $refund->id . '/process', [
            'idempotency_key' => 'refund.process.' . $refund->id,
        ]);

        if ($response->status() >= 400) {
            $this->error = $response->json('message') ?? 'Failed to process refund.';
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
