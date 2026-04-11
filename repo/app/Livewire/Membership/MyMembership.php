<?php

namespace App\Livewire\Membership;

use Livewire\Component;

class MyMembership extends Component
{
    public function render()
    {
        $user   = auth()->user();
        $active = $user->activeMembership();
        $orders = $user->membershipOrders()->with('plan')->latest()->get();

        return view('livewire.membership.my-membership', compact('active', 'orders'))
            ->layout('layouts.app', ['title' => 'My Membership']);
    }
}
