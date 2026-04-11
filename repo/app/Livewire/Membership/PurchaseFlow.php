<?php

namespace App\Livewire\Membership;

use App\Models\MembershipPlan;
use App\Services\MembershipService;
use Illuminate\Support\Str;
use Livewire\Component;
use RuntimeException;

class PurchaseFlow extends Component
{
    public MembershipPlan $plan;
    public bool $confirmed = false;
    public string $error   = '';

    /**
     * Caller-stable idempotency key for this purchase attempt.
     * Generated once per component mount and reused across submit retries
     * (double-clicks, transient errors) so MembershipService::purchase
     * collapses duplicate submissions onto the same MembershipOrder row.
     */
    public string $idempotencyKey = '';

    public function mount(MembershipPlan $plan): void
    {
        $this->plan = $plan;
        $this->idempotencyKey = (string) Str::uuid();
    }

    public function confirm(): void
    {
        $this->confirmed = true;
    }

    public function submit(MembershipService $service)
    {
        try {
            $service->purchase(auth()->user(), $this->plan, $this->idempotencyKey);
        } catch (RuntimeException $e) {
            $this->error = $e->getMessage();
            return;
        }

        return redirect()->route('membership.my')
            ->with('success', 'Order created. Proceed to payment.');
    }

    public function render()
    {
        return view('livewire.membership.purchase-flow')
            ->layout('layouts.app', ['title' => 'Purchase Membership']);
    }
}
