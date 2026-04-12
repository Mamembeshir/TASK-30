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
use App\Services\ApiClient;
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

    public function confirmPayment(string $paymentId): void
    {
        $payment = Payment::findOrFail($paymentId);

        $response = app(ApiClient::class)->post('/payments/' . $payment->id . '/confirm', [
            'confirmation_event_id' => 'manual-' . $payment->id,
        ]);

        if ($response->status() >= 400) {
            $this->dispatch('notify', type: 'error', message: $response->json('message') ?? 'Failed to confirm payment.');
            return;
        }

        $this->dispatch('notify', type: 'success', message: 'Payment confirmed.');
    }

    public function voidPayment(string $paymentId): void
    {
        $payment = Payment::findOrFail($paymentId);

        $response = app(ApiClient::class)->post('/payments/' . $payment->id . '/void', [
            'idempotency_key' => 'payment.void.' . $payment->id,
        ]);

        if ($response->status() >= 400) {
            $this->dispatch('notify', type: 'error', message: $response->json('message') ?? 'Failed to void payment.');
            return;
        }

        $this->dispatch('notify', type: 'success', message: 'Payment voided.');
    }

    public function closeSettlement(): void
    {
        $date = $this->settlementDate ?: now()->toDateString();

        $response = app(ApiClient::class)->post('/settlements/close', [
            'date'            => $date,
            'idempotency_key' => 'settlement.close.' . $date,
        ]);

        if ($response->status() >= 400) {
            $this->addError('settlement', $response->json('message') ?? 'Failed to close settlement.');
            return;
        }

        $this->dispatch('notify', type: 'success', message: 'Settlement closed.');
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
