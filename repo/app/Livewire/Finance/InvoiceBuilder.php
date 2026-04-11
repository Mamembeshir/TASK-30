<?php

namespace App\Livewire\Finance;

use App\Enums\LineItemType;
use App\Enums\UserRole;
use App\Models\Invoice;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Component;

class InvoiceBuilder extends Component
{
    public ?Invoice $invoice = null;

    // New line item form
    public string $lineDescription = '';
    public string $lineAmount      = '';
    public string $lineType        = '';

    public string $userSearch     = '';
    public ?string $selectedUserId = null;
    public array $userResults      = [];

    /**
     * Per-component idempotency key for invoice creation. Initialized once
     * per Livewire component instance so that a retry of `createInvoice`
     * from the same browser session collapses onto the same invoice row
     * instead of burning a new MV-YYYY-NNNNN number every click.
     */
    public ?string $createIdempotencyKey = null;

    public function mount(?Invoice $invoice = null): void
    {
        Gate::allowIf(auth()->user()->hasRole(UserRole::FINANCE_SPECIALIST) || auth()->user()->isAdmin());
        if ($invoice?->exists) {
            $this->invoice = $invoice;
        }
    }

    public function searchUsers(): void
    {
        if (strlen($this->userSearch) < 2) {
            $this->userResults = [];
            return;
        }
        $this->userResults = User::where(fn ($q) =>
            $q->where('email', 'ilike', "%{$this->userSearch}%")
              ->orWhere('username', 'ilike', "%{$this->userSearch}%")
        )->limit(10)->get()->map(fn ($u) => [
            'id'    => (string) $u->id,
            'label' => "{$u->email} ({$u->username})",
        ])->toArray();
    }

    public function selectUser(string $userId): void
    {
        $user = User::find($userId);
        if ($user) {
            $this->selectedUserId = $userId;
            $this->userSearch     = $user->email;
            $this->userResults    = [];
        }
    }

    public function createInvoice(InvoiceService $service): void
    {
        $this->validate(['selectedUserId' => 'required|uuid|exists:users,id']);
        $user = User::findOrFail($this->selectedUserId);

        $this->createIdempotencyKey ??= (string) Str::uuid();

        $this->invoice = $service->createInvoice($user, $this->createIdempotencyKey);
        $this->dispatch('notify', type: 'success', message: 'Invoice created.');
    }

    public function addLine(InvoiceService $service): void
    {
        $this->validate([
            'lineDescription' => 'required|string|max:500',
            'lineAmount'      => 'required|numeric|min:0.01',
            'lineType'        => 'required|in:' . implode(',', array_column(LineItemType::cases(), 'value')),
        ]);

        $amountCents = (int) round((float) $this->lineAmount * 100);

        try {
            $service->addLineItem(
                $this->invoice,
                $this->lineDescription,
                $amountCents,
                LineItemType::from($this->lineType)
            );
            $this->invoice = $this->invoice->fresh();
            $this->lineDescription = '';
            $this->lineAmount      = '';
            $this->lineType        = '';
        } catch (\RuntimeException $e) {
            $this->addError('line', $e->getMessage());
        }
    }

    public function issue(InvoiceService $service)
    {
        try {
            $this->invoice = $service->issueInvoice(
                $this->invoice,
                'invoice.issue.' . $this->invoice->id,
            );
            return redirect()->route('finance.invoices.show', $this->invoice)
                ->with('success', 'Invoice issued.');
        } catch (\RuntimeException $e) {
            $this->addError('issue', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.finance.invoice-builder', [
            'lineTypes' => LineItemType::cases(),
        ])->layout('layouts.app', ['title' => $this->invoice ? 'Edit Invoice' : 'New Invoice']);
    }
}
