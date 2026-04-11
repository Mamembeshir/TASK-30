<?php

namespace App\Livewire\Finance;

use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Payment;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class PaymentIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $filterStatus = '';

    public function mount(): void
    {
        Gate::allowIf(auth()->user()->hasRole(UserRole::FINANCE_SPECIALIST) || auth()->user()->isAdmin());
    }

    public function updatedFilterStatus(): void { $this->resetPage(); }

    public function render()
    {
        $payments = Payment::with('user')
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->latest()
            ->paginate(25);

        return view('livewire.finance.payment-index', [
            'payments' => $payments,
            'statuses' => PaymentStatus::cases(),
        ])->layout('layouts.app', ['title' => 'Payments']);
    }
}
