<div>
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Refund Approval</h1>

    @if($error)
        <div class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-700">{{ $error }}</div>
    @endif

    <div class="rounded-xl border border-gray-200 bg-white overflow-hidden">
        @if($refunds->isEmpty())
            <p class="px-6 py-8 text-center text-sm text-gray-500">No refunds to review.</p>
        @else
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left font-medium text-gray-500">Member</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500">Type</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500">Amount</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500">Reason</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500">Status</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500">Approved By</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($refunds as $refund)
                        <tr>
                            <td class="px-6 py-4 font-medium text-gray-900">
                                {{ $refund->payment->user->name ?? $refund->payment->user->username ?? '—' }}
                            </td>
                            <td class="px-6 py-4 text-gray-600">{{ $refund->refund_type->label() }}</td>
                            <td class="px-6 py-4 text-gray-900">{{ $refund->formattedAmount() }}</td>
                            <td class="px-6 py-4 text-gray-600 max-w-xs truncate">{{ $refund->reason }}</td>
                            <td class="px-6 py-4">
                                @php
                                    $statusColors = match($refund->status->value) {
                                        'PENDING'   => 'bg-yellow-100 text-yellow-700',
                                        'APPROVED'  => 'bg-blue-100 text-blue-700',
                                        'PROCESSED' => 'bg-green-100 text-green-700',
                                        'REJECTED'  => 'bg-red-100 text-red-700',
                                        default     => 'bg-gray-100 text-gray-700',
                                    };
                                @endphp
                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $statusColors }}">
                                    {{ $refund->status->label() }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-gray-600">{{ $refund->approver?->username ?? '—' }}</td>
                            <td class="px-6 py-4">
                                <div class="flex gap-2">
                                    @if($refund->status->value === 'PENDING')
                                        <button wire:click="approve('{{ $refund->id }}')"
                                                class="rounded bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700">
                                            Approve
                                        </button>
                                    @elseif($refund->status->value === 'APPROVED')
                                        <button wire:click="process('{{ $refund->id }}')"
                                                class="rounded bg-green-600 px-3 py-1 text-xs font-medium text-white hover:bg-green-700">
                                            Process
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="px-6 py-4 border-t border-gray-200">
                {{ $refunds->links() }}
            </div>
        @endif
    </div>
</div>
