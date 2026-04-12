<?php

namespace App\Livewire\Finance;

use App\Enums\UserRole;
use App\Models\Settlement;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class StatementExport extends Component
{
    public ?string $settlementId = null;

    public function mount(): void
    {
        Gate::allowIf(auth()->user()->hasRole(UserRole::FINANCE_SPECIALIST) || auth()->user()->isAdmin());
    }

    public function download()
    {
        $this->validate(['settlementId' => 'required|uuid|exists:settlements,id']);

        $settlement = Settlement::findOrFail($this->settlementId);

        return redirect(url('/api/settlements/' . $settlement->id . '/statement'));
    }

    public function render()
    {
        $settlements = Settlement::orderByDesc('settlement_date')->limit(30)->get();

        return view('livewire.finance.statement-export', compact('settlements'))
            ->layout('layouts.app', ['title' => 'Export Statement']);
    }
}
