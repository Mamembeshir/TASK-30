<?php

namespace App\Livewire\Finance;

use App\Enums\UserRole;
use App\Models\Settlement;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class SettlementIndex extends Component
{
    use WithPagination;

    public function mount(): void
    {
        Gate::allowIf(auth()->user()->hasRole(UserRole::FINANCE_SPECIALIST) || auth()->user()->isAdmin());
    }

    public function render()
    {
        $settlements = Settlement::orderByDesc('settlement_date')->paginate(20);

        return view('livewire.finance.settlement-index', compact('settlements'))
            ->layout('layouts.app', ['title' => 'Settlements']);
    }
}
