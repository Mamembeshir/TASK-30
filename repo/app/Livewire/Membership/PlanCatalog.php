<?php

namespace App\Livewire\Membership;

use App\Models\MembershipPlan;
use Livewire\Component;

class PlanCatalog extends Component
{
    public function render()
    {
        $plans  = MembershipPlan::where('is_active', true)->orderBy('price_cents')->get();
        $active = auth()->user()->activeMembership();

        return view('livewire.membership.plan-catalog', compact('plans', 'active'))
            ->layout('layouts.app', ['title' => 'Membership Plans']);
    }
}
