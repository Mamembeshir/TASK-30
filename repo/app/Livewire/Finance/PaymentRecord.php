<?php

namespace App\Livewire\Finance;

use App\Enums\TenderType;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Component;

class PaymentRecord extends Component
{
    public string  $userSearch       = '';
    public ?string $selectedUserId   = null;
    public string  $tenderType       = '';
    public string  $amountInput      = '';
    public string  $referenceNumber  = '';

    /** @var array<int, array{id: string, label: string}> */
    public array $userResults = [];

    /**
     * Caller-stable idempotency key for this PaymentRecord form instance.
     * Generated once at mount and reused across submit retries so duplicate
     * submissions collapse onto a single Payment row via PaymentService::recordPayment.
     * After a successful submit the form redirects, so a fresh visit gets a new key.
     */
    public string $idempotencyKey = '';

    public function mount(): void
    {
        Gate::allowIf(auth()->user()->hasRole(UserRole::FINANCE_SPECIALIST) || auth()->user()->isAdmin());
        $this->idempotencyKey = (string) Str::uuid();
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

    public function submit(PaymentService $service)
    {
        $this->validate([
            'selectedUserId' => 'required|uuid|exists:users,id',
            'tenderType'     => 'required|in:' . implode(',', array_column(TenderType::cases(), 'value')),
            'amountInput'    => 'required|numeric|min:0.01',
            'referenceNumber' => 'nullable|string|max:100',
        ]);

        $amountCents = (int) round((float) $this->amountInput * 100);

        try {
            $payment = $service->recordPayment(
                User::findOrFail($this->selectedUserId),
                TenderType::from($this->tenderType),
                $amountCents,
                $this->referenceNumber ?: null,
                $this->idempotencyKey,
            );

            return redirect()->route('finance.payments.show', $payment)
                ->with('success', 'Payment recorded.');
        } catch (\RuntimeException $e) {
            $this->addError('form', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.finance.payment-record', [
            'tenderTypes' => TenderType::cases(),
        ])->layout('layouts.app', ['title' => 'Record Payment']);
    }
}
