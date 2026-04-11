<?php

namespace App\Livewire\Finance;

use App\Enums\UserRole;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
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

    public function confirm(PaymentService $service): void
    {
        $eventId = $this->confirmEventId ?: 'manual-' . $this->payment->id;
        try {
            $this->payment = $service->confirmPayment($this->payment, $eventId);
            $this->dispatch('notify', type: 'success', message: 'Payment confirmed.');
        } catch (\RuntimeException $e) {
            $this->addError('confirm', $e->getMessage());
        }
    }

    public function void(PaymentService $service): void
    {
        try {
            $this->payment = $service->voidPayment(
                $this->payment,
                'payment.void.' . $this->payment->id,
            );
            $this->showVoidConfirm = false;
            $this->dispatch('notify', type: 'success', message: 'Payment voided.');
        } catch (\RuntimeException $e) {
            $this->addError('void', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.finance.payment-detail')
            ->layout('layouts.app', ['title' => 'Payment #' . substr($this->payment->id, 0, 8)]);
    }
}
