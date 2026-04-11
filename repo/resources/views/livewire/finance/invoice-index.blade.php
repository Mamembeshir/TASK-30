<div>
    <x-page-header title="Invoices"
        :breadcrumbs="[['label'=>'Dashboard','route'=>'dashboard'],['label'=>'Finance','route'=>'finance.index'],['label'=>'Invoices']]"/>

    <div class="mb-4 flex flex-wrap gap-3">
        <select wire:model.live="filterStatus"
                class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="">All statuses</option>
            <option value="DRAFT">Draft</option>
            <option value="ISSUED">Issued</option>
            <option value="PAID">Paid</option>
            <option value="VOIDED">Voided</option>
        </select>
        <a href="{{ route('finance.invoices.create') }}"
           class="ml-auto rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
            New Invoice
        </a>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white overflow-hidden">
        @if($invoices->isEmpty())
            <p class="px-6 py-8 text-center text-sm text-gray-500">No invoices found.</p>
        @else
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left font-medium text-gray-500">Invoice #</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500">Member</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500">Total</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500">Status</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500">Issued</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500">Due</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($invoices as $invoice)
                        <tr>
                            <td class="px-6 py-4 font-mono text-xs text-gray-900">{{ $invoice->invoice_number }}</td>
                            <td class="px-6 py-4 text-gray-900">{{ $invoice->user->email }}</td>
                            <td class="px-6 py-4 text-gray-900">{{ $invoice->formattedTotal() }}</td>
                            <td class="px-6 py-4">
                                @php $sc = match($invoice->status->value) {
                                    'DRAFT'  => 'bg-gray-100 text-gray-700',
                                    'ISSUED' => 'bg-blue-100 text-blue-700',
                                    'PAID'   => 'bg-green-100 text-green-700',
                                    'VOIDED' => 'bg-red-100 text-red-700',
                                    default  => 'bg-gray-100 text-gray-700',
                                }; @endphp
                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $sc }}">
                                    {{ $invoice->status->label() }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-gray-500">{{ $invoice->issued_at?->format('M j, Y') ?? '—' }}</td>
                            <td class="px-6 py-4 text-gray-500">{{ $invoice->due_date?->format('M j, Y') ?? '—' }}</td>
                            <td class="px-6 py-4">
                                <a href="{{ route('finance.invoices.show', $invoice) }}"
                                   class="text-indigo-600 hover:underline text-xs">View</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="px-6 py-4 border-t border-gray-200">
                {{ $invoices->links() }}
            </div>
        @endif
    </div>
</div>
