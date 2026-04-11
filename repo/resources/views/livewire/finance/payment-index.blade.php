<div>
    <x-page-header title="Payments"
        :breadcrumbs="[['label'=>'Dashboard','route'=>'dashboard'],['label'=>'Finance','route'=>'finance.index'],['label'=>'Payments']]"/>

    <div class="mb-4 flex flex-wrap gap-3">
        <select wire:model.live="filterStatus"
                class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="">All statuses</option>
            <option value="RECORDED">Recorded</option>
            <option value="CONFIRMED">Confirmed</option>
            <option value="VOIDED">Voided</option>
            <option value="REFUNDED">Refunded</option>
        </select>
        <a href="{{ route('finance.payments.record') }}"
           class="ml-auto rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
            Record Payment
        </a>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white overflow-hidden">
        @if($payments->isEmpty())
            <p class="px-6 py-8 text-center text-sm text-gray-500">No payments found.</p>
        @else
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left font-medium text-gray-500">ID</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500">Member</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500">Amount</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500">Tender</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500">Status</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500">Date</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($payments as $payment)
                        <tr>
                            <td class="px-6 py-4 font-mono text-xs text-gray-500">{{ substr($payment->id, 0, 8) }}</td>
                            <td class="px-6 py-4 text-gray-900">{{ $payment->user->email }}</td>
                            <td class="px-6 py-4 text-gray-900">{{ $payment->formattedAmount() }}</td>
                            <td class="px-6 py-4 text-gray-600">{{ $payment->tender_type->label() }}</td>
                            <td class="px-6 py-4">
                                @php $sc = match($payment->status->value) {
                                    'RECORDED'  => 'bg-yellow-100 text-yellow-700',
                                    'CONFIRMED' => 'bg-green-100 text-green-700',
                                    'VOIDED'    => 'bg-red-100 text-red-700',
                                    default     => 'bg-gray-100 text-gray-700',
                                }; @endphp
                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $sc }}">
                                    {{ $payment->status->label() }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-gray-500">{{ $payment->created_at->format('M j, Y') }}</td>
                            <td class="px-6 py-4">
                                <a href="{{ route('finance.payments.show', $payment) }}"
                                   class="text-indigo-600 hover:underline text-xs">View</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="px-6 py-4 border-t border-gray-200">
                {{ $payments->links() }}
            </div>
        @endif
    </div>
</div>
