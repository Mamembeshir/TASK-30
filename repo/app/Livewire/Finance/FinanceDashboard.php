<?php

namespace App\Livewire\Finance;

use App\Enums\ExceptionStatus;
use App\Enums\InvoiceStatus;
use App\Enums\RefundStatus;
use App\Enums\UserRole;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Settlement;
use App\Services\PaymentService;
use App\Services\SettlementService;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class FinanceDashboard extends Component
{
    public string $tab            = 'payments';
    public string $settlementDate = '';

    public function mount(): void
    {
        Gate::allowIf(auth()->user()->hasRole(UserRole::FINANCE_SPECIALIST) || auth()->user()->isAdmin());
        $this->settlementDate = now()->toDateString();
    }

    public function confirmPayment(string $paymentId, PaymentService $service): void
    {
        $payment = Payment::findOrFail($paymentId);
        try {
            $service->confirmPayment($payment, 'manual-' . $payment->id);
            $this->dispatch('notify', type: 'success', message: 'Payment confirmed.');
        } catch (\RuntimeException $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    public function voidPayment(string $paymentId, PaymentService $service): void
    {
        $payment = Payment::findOrFail($paymentId);
        try {
            $service->voidPayment($payment);
            $this->dispatch('notify', type: 'success', message: 'Payment voided.');
        } catch (\RuntimeException $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    public function closeSettlement(SettlementService $service): void
    {
        $date = $this->settlementDate ?: now()->toDateString();
        try {
            $service->closeDailySettlement($date);
            $this->dispatch('notify', type: 'success', message: 'Settlement closed.');
        } catch (\RuntimeException $e) {
            $this->addError('settlement', $e->getMessage());
        }
    }

    public function render()
    {
        $payments   = Payment::with('user')->latest()->limit(20)->get();
        $refunds    = Refund::where('status', RefundStatus::APPROVED->value)->with('payment.user')->latest()->limit(20)->get();
        $settlements = Settlement::orderByDesc('settlement_date')->limit(10)->get();
        $exceptions  = \App\Models\SettlementException::where('status', ExceptionStatus::OPEN->value)
            ->with('settlement')->latest()->get();
        $invoices    = Invoice::whereIn('status', [InvoiceStatus::DRAFT->value, InvoiceStatus::ISSUED->value])
            ->with('user')->latest()->limit(20)->get();

        return view('livewire.finance.finance-dashboard', compact(
            'payments', 'refunds', 'settlements', 'exceptions', 'invoices'
        ))->layout('layouts.app', ['title' => 'Finance Dashboard']);
    }
}
