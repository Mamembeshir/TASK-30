<?php

namespace App\Livewire\Finance;

use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
use App\Models\Invoice;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class InvoiceIndex extends Component
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
        $invoices = Invoice::with('user')
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->latest()
            ->paginate(25);

        return view('livewire.finance.invoice-index', [
            'invoices' => $invoices,
            'statuses' => InvoiceStatus::cases(),
        ])->layout('layouts.app', ['title' => 'Invoices']);
    }
}
