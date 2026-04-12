<?php

namespace App\Livewire\Finance;

use App\Enums\UserRole;
use App\Models\Invoice;
use App\Services\ApiClient;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class InvoiceDetail extends Component
{
    public Invoice $invoice;

    public bool $showVoidConfirm = false;

    public function mount(Invoice $invoice): void
    {
        $this->invoice = $invoice;
        Gate::allowIf(auth()->user()->hasRole(UserRole::FINANCE_SPECIALIST) || auth()->user()->isAdmin());
    }

    public function markPaid(): void
    {
        $response = app(ApiClient::class)->post('/invoices/' . $this->invoice->id . '/mark-paid', [
            'idempotency_key' => 'invoice.mark_paid.' . $this->invoice->id,
        ]);

        if ($response->status() >= 400) {
            $this->addError('paid', $response->json('message') ?? 'Failed to mark invoice as paid.');
            return;
        }

        $this->invoice = Invoice::find($response->json('id')) ?? $this->invoice->fresh();
        $this->dispatch('notify', type: 'success', message: 'Invoice marked as paid.');
    }

    public function void(): void
    {
        $response = app(ApiClient::class)->post('/invoices/' . $this->invoice->id . '/void', [
            'idempotency_key' => 'invoice.void.' . $this->invoice->id,
        ]);

        if ($response->status() >= 400) {
            $this->addError('void', $response->json('message') ?? 'Failed to void invoice.');
            return;
        }

        $this->invoice        = Invoice::find($response->json('id')) ?? $this->invoice->fresh();
        $this->showVoidConfirm = false;
        $this->dispatch('notify', type: 'success', message: 'Invoice voided.');
    }

    public function render()
    {
        return view('livewire.finance.invoice-detail', [
            'lineItems' => $this->invoice->lineItems,
        ])->layout('layouts.app', ['title' => 'Invoice ' . $this->invoice->invoice_number]);
    }
}
