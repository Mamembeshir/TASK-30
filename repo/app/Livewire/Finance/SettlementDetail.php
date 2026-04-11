<?php

namespace App\Livewire\Finance;

use App\Enums\ExceptionStatus;
use App\Enums\UserRole;
use App\Models\Settlement;
use App\Models\SettlementException;
use App\Services\SettlementService;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class SettlementDetail extends Component
{
    public Settlement $settlement;

    public ?string $resolveExceptionId = null;
    public string  $resolutionType     = '';
    public string  $resolutionNote     = '';

    public function mount(Settlement $settlement): void
    {
        $this->settlement = $settlement;
        Gate::allowIf(auth()->user()->hasRole(UserRole::FINANCE_SPECIALIST) || auth()->user()->isAdmin());
    }

    public function resolveException(SettlementService $service): void
    {
        $this->validate([
            'resolveExceptionId' => 'required|uuid',
            'resolutionType'     => 'required|in:RESOLVED,WRITTEN_OFF',
            'resolutionNote'     => 'required|string|min:5',
        ]);

        $exception = SettlementException::findOrFail($this->resolveExceptionId);
        try {
            $service->resolveException(
                $exception,
                ExceptionStatus::from($this->resolutionType),
                $this->resolutionNote,
                auth()->user(),
                'settlement_exception.resolve.' . $exception->id,
            );
            $this->resolveExceptionId = null;
            $this->resolutionType     = '';
            $this->resolutionNote     = '';
            $this->settlement         = $this->settlement->fresh();
            $this->dispatch('notify', type: 'success', message: 'Exception resolved.');
        } catch (\RuntimeException $e) {
            $this->addError('resolve', $e->getMessage());
        }
    }

    public function reReconcile(SettlementService $service): void
    {
        try {
            $this->settlement = $service->reReconcile(
                $this->settlement,
                'settlement.reconcile.' . $this->settlement->id,
            );
            $this->dispatch('notify', type: 'success', message: 'Settlement reconciled.');
        } catch (\RuntimeException $e) {
            $this->addError('reconcile', $e->getMessage());
        }
    }

    public function downloadStatement(SettlementService $service)
    {
        $path = $service->exportStatement($this->settlement);
        return response()->download(storage_path("app/{$path}"));
    }

    public function render()
    {
        return view('livewire.finance.settlement-detail', [
            'payments'   => $this->settlement->payments()->with('user')->get(),
            'exceptions' => $this->settlement->exceptions()->get(),
        ])->layout('layouts.app', ['title' => 'Settlement ' . $this->settlement->settlement_date->format('M j, Y')]);
    }
}
