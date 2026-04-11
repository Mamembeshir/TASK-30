<?php

namespace App\Livewire\Finance;

use App\Enums\UserRole;
use App\Models\Settlement;
use App\Services\SettlementService;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class StatementExport extends Component
{
    public ?string $settlementId = null;

    public function mount(): void
    {
        Gate::allowIf(auth()->user()->hasRole(UserRole::FINANCE_SPECIALIST) || auth()->user()->isAdmin());
    }

    public function download(SettlementService $service)
    {
        $this->validate(['settlementId' => 'required|uuid|exists:settlements,id']);

        $settlement = Settlement::findOrFail($this->settlementId);

        try {
            $path = $service->exportStatement($settlement);
            return response()->download(storage_path("app/{$path}"));
        } catch (\Exception $e) {
            $this->addError('download', $e->getMessage());
        }
    }

    public function render()
    {
        $settlements = Settlement::orderByDesc('settlement_date')->limit(30)->get();

        return view('livewire.finance.statement-export', compact('settlements'))
            ->layout('layouts.app', ['title' => 'Export Statement']);
    }
}
