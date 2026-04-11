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

    public function mount(MembershipPlan $plan): void
    {
        $this->plan = $plan;
    }

    public function confirm(): void
    {
        $this->confirmed = true;
    }

    public function submit(MembershipService $service)
    {
        $key = (string) Str::uuid();

        try {
            $service->topUp(auth()->user(), $this->plan, $key);
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
