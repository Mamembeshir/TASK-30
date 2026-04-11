<?php

namespace App\Livewire\Membership;

use Livewire\Component;

class OrderHistory extends Component
{
    public function mount()
    {
        return redirect()->route('membership.my');
    }

    public function render()
    {
        return view('livewire.membership.order-history')
            ->layout('layouts.app', ['title' => 'Order History']);
    }
}
