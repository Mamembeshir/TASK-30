<?php

namespace App\Livewire\Membership;

use App\Models\MembershipOrder;
use App\Services\ApiClient;
use Livewire\Component;

class RefundRequest extends Component
{
    public MembershipOrder $order;
    public string $refundType  = 'FULL';
    public string $amountInput = '';
    public string $reason      = '';
    public string $error       = '';

    protected function rules(): array
    {
        return [
            'refundType'   => 'required|in:FULL,PARTIAL',
            'amountInput'  => 'required_if:refundType,PARTIAL|nullable|numeric|min:0.01',
            'reason'       => 'required|string|min:10',
        ];
    }

    public function mount(MembershipOrder $order): void
    {
        abort_if((string) $order->user_id !== (string) auth()->id(), 403);

        $this->order = $order;
    }

    public function submit()
    {
        $this->validate();

        // Re-check ownership in case the component state was tampered with.
        abort_if((string) $this->order->user_id !== (string) auth()->id(), 403);

        $amountCents = $this->refundType === 'PARTIAL'
            ? (int) round((float) $this->amountInput * 100)
            : null;

        // Deterministic key: same user + order always maps to the same string so
        // that re-submits (double-clicks, back-button repost) collapse onto the
        // existing Refund row via MembershipService::requestRefund's dedupe.
        $key = 'refund-order-' . $this->order->id . '-' . auth()->id();

        $body = [
            'refund_type'     => $this->refundType,
            'reason'          => $this->reason,
            'idempotency_key' => $key,
        ];

        if ($amountCents !== null) {
            $body['amount_cents'] = $amountCents;
        }

        $response = app(ApiClient::class)->post('/membership/orders/' . $this->order->id . '/refund', $body);

        if ($response->status() >= 400) {
            $this->error = $response->json('message') ?? 'Failed to submit refund request.';
            return;
        }

        return redirect()->route('membership.my')
            ->with('success', 'Refund request submitted.');
    }

    public function render()
    {
        return view('livewire.membership.refund-request')
            ->layout('layouts.app', ['title' => 'Request Refund']);
    }
}
