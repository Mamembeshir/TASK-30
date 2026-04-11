<?php

namespace App\Livewire\Membership;

use App\Enums\RefundType;
use App\Models\MembershipOrder;
use App\Services\MembershipService;
use Illuminate\Support\Str;
use Livewire\Component;
use RuntimeException;

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
        $this->order = $order;
    }

    public function submit(MembershipService $service)
    {
        $this->validate();

        $type         = RefundType::from($this->refundType);
        $amountCents  = $this->refundType === 'PARTIAL'
            ? (int) round((float) $this->amountInput * 100)
            : null;
        $key = (string) Str::uuid();

        try {
            $service->requestRefund($this->order, $type, $this->reason, $amountCents, $key);
        } catch (RuntimeException $e) {
            $this->error = $e->getMessage();
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
