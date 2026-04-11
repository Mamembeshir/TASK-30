<?php

namespace App\Livewire\Membership;

use App\Models\MembershipPlan;
use App\Services\MembershipService;
use Illuminate\Support\Str;
use Livewire\Component;
use RuntimeException;

class TopUpFlow extends Component
{
    public MembershipPlan $plan;
    public bool $confirmed = false;
    public string $error   = '';

    /**
     * Caller-stable idempotency key — see PurchaseFlow for rationale.
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
            $service->topUp(auth()->user(), $this->plan, $this->idempotencyKey);
        } catch (RuntimeException $e) {
            $this->error = $e->getMessage();
            return;
        }

        return redirect()->route('membership.my')
            ->with('success', 'Upgrade order created. Proceed to payment.');
    }

    public function render()
    {
        $active    = auth()->user()->activeMembership();
        $priceDiff = $active
            ? max(0, $this->plan->price_cents - $active->plan->price_cents)
            : $this->plan->price_cents;

        return view('livewire.membership.top-up-flow', compact('active', 'priceDiff'))
            ->layout('layouts.app', ['title' => 'Upgrade Membership']);
    }
}
