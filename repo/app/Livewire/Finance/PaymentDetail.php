<?php

namespace App\Livewire\Finance;

use App\Enums\UserRole;
use App\Models\Payment;
use App\Services\ApiClient;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class PaymentDetail extends Component
{
    public Payment $payment;

    public bool   $showVoidConfirm = false;
    public string $confirmEventId  = '';

    public function mount(Payment $payment): void
    {
        $this->payment = $payment;
        Gate::allowIf(auth()->user()->hasRole(UserRole::FINANCE_SPECIALIST) || auth()->user()->isAdmin());
    }

    public function confirm(): void
    {
        $eventId = $this->confirmEventId ?: 'manual-' . $this->payment->id;

        $response = app(ApiClient::class)->post('/payments/' . $this->payment->id . '/confirm', [
            'confirmation_event_id' => $eventId,
        ]);

        if ($response->status() >= 400) {
            $this->addError('confirm', $response->json('message') ?? 'Failed to confirm payment.');
            return;
        }

        $this->payment = Payment::find($response->json('id')) ?? $this->payment->fresh();
        $this->dispatch('notify', type: 'success', message: 'Payment confirmed.');
    }

    public function void(): void
    {
        $response = app(ApiClient::class)->post('/payments/' . $this->payment->id . '/void', [
            'idempotency_key' => 'payment.void.' . $this->payment->id,
        ]);

        if ($response->status() >= 400) {
            $this->addError('void', $response->json('message') ?? 'Failed to void payment.');
            return;
        }

        $this->payment        = Payment::find($response->json('id')) ?? $this->payment->fresh();
        $this->showVoidConfirm = false;
        $this->dispatch('notify', type: 'success', message: 'Payment voided.');
    }

    public function render()
    {
        return view('livewire.finance.payment-detail')
            ->layout('layouts.app', ['title' => 'Payment #' . substr($this->payment->id, 0, 8)]);
    }
}
