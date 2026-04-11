<?php

namespace App\Livewire\Finance;

use App\Enums\UserRole;
use App\Models\Invoice;
use App\Services\InvoiceService;
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

    public function markPaid(InvoiceService $service): void
    {
        try {
            $this->invoice = $service->markPaid($this->invoice);
            $this->dispatch('notify', type: 'success', message: 'Invoice marked as paid.');
        } catch (\RuntimeException $e) {
            $this->addError('paid', $e->getMessage());
        }
    }

    public function void(InvoiceService $service): void
    {
        try {
            $this->invoice = $service->voidInvoice($this->invoice);
            $this->showVoidConfirm = false;
            $this->dispatch('notify', type: 'success', message: 'Invoice voided.');
        } catch (\RuntimeException $e) {
            $this->addError('void', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.finance.invoice-detail', [
            'lineItems' => $this->invoice->lineItems,
        ])->layout('layouts.app', ['title' => 'Invoice ' . $this->invoice->invoice_number]);
    }
}
