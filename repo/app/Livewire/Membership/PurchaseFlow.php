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
            $service->purchase(auth()->user(), $this->plan, $key);
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
