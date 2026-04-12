<?php

namespace App\Livewire\Finance;

use App\Enums\ExceptionStatus;
use App\Enums\UserRole;
use App\Models\Settlement;
use App\Models\SettlementException;
use App\Services\ApiClient;
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

    public function resolveException(): void
    {
        $this->validate([
            'resolveExceptionId' => 'required|uuid',
            'resolutionType'     => 'required|in:RESOLVED,WRITTEN_OFF',
            'resolutionNote'     => 'required|string|min:5',
        ]);

        $response = app(ApiClient::class)->post('/settlements/' . $this->settlement->id . '/resolve-exception', [
            'exception_id'    => $this->resolveExceptionId,
            'resolution_type' => $this->resolutionType,
            'resolution_note' => $this->resolutionNote,
            'idempotency_key' => 'settlement_exception.resolve.' . $this->resolveExceptionId,
        ]);

        if ($response->status() === 404) {
            abort(404);
        }

        if ($response->status() >= 400) {
            $this->addError('resolve', $response->json('message') ?? 'Failed to resolve exception.');
            return;
        }

        $this->resolveExceptionId = null;
        $this->resolutionType     = '';
        $this->resolutionNote     = '';
        $this->settlement         = $this->settlement->fresh();
        $this->dispatch('notify', type: 'success', message: 'Exception resolved.');
    }

    public function reReconcile(): void
    {
        $response = app(ApiClient::class)->post('/settlements/' . $this->settlement->id . '/re-reconcile', [
            'idempotency_key' => 'settlement.reconcile.' . $this->settlement->id,
        ]);

        if ($response->status() >= 400) {
            $this->addError('reconcile', $response->json('message') ?? 'Failed to re-reconcile settlement.');
            return;
        }

        $this->settlement = Settlement::find($response->json('id')) ?? $this->settlement->fresh();
        $this->dispatch('notify', type: 'success', message: 'Settlement reconciled.');
    }

    public function downloadStatement()
    {
        return redirect(url('/api/settlements/' . $this->settlement->id . '/statement'));
    }

    public function render()
    {
        return view('livewire.finance.settlement-detail', [
            'payments'   => $this->settlement->payments()->with('user')->get(),
            'exceptions' => $this->settlement->exceptions()->get(),
        ])->layout('layouts.app', ['title' => 'Settlement ' . $this->settlement->settlement_date->format('M j, Y')]);
    }
}
