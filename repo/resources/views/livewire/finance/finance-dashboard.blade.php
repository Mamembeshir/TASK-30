<div>
    <x-page-header title="Finance Dashboard" :breadcrumbs="[['label'=>'Dashboard','route'=>'dashboard'],['label'=>'Finance']]"/>

    @if($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-700">
            @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    {{-- Tab nav --}}
    <div class="mb-6 border-b border-gray-200">
        <nav class="-mb-px flex gap-4 text-sm font-medium">
            @foreach(['payments' => 'Payments', 'refunds' => 'Refunds', 'settlement' => 'Settlement', 'exceptions' => 'Exceptions', 'invoices' => 'Invoices'] as $t => $label)
                <button wire:click="$set('tab', '{{ $t }}')"
                        class="{{ $tab === $t ? 'border-b-2 border-indigo-600 text-indigo-600' : 'text-gray-500 hover:text-gray-700' }} pb-3 px-1">
                    {{ $label }}
                </button>
            @endforeach
        </nav>
    </div>

    {{-- Payments tab --}}
    @if($tab === 'payments')
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-800">Recent Payments</h2>
            <a href="{{ route('finance.payments') }}" class="text-sm text-indigo-600 hover:underline">View all</a>
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
            @endif
        </div>
    @endif

    {{-- Refunds tab --}}
    @if($tab === 'refunds')
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-800">Recent Refunds</h2>
            <a href="{{ route('finance.refunds') }}" class="text-sm text-indigo-600 hover:underline">Manage refunds</a>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white overflow-hidden">
            @if($refunds->isEmpty())
                <p class="px-6 py-8 text-center text-sm text-gray-500">No refunds found.</p>
            @else
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left font-medium text-gray-500">Member</th>
                            <th class="px-6 py-3 text-left font-medium text-gray-500">Amount</th>
                            <th class="px-6 py-3 text-left font-medium text-gray-500">Type</th>
                            <th class="px-6 py-3 text-left font-medium text-gray-500">Status</th>
                            <th class="px-6 py-3 text-left font-medium text-gray-500">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($refunds as $refund)
                            <tr>
                                <td class="px-6 py-4 text-gray-900">{{ $refund->payment->user->email }}</td>
                                <td class="px-6 py-4 text-gray-900">{{ $refund->formattedAmount() }}</td>
                                <td class="px-6 py-4 text-gray-600">{{ $refund->refund_type->label() }}</td>
                                <td class="px-6 py-4">
                                    @php $sc = match($refund->status->value) {
                                        'RECORDED'  => 'bg-yellow-100 text-yellow-700',
                                        'APPROVED'  => 'bg-blue-100 text-blue-700',
                                        'PROCESSED' => 'bg-green-100 text-green-700',
                                        'REJECTED'  => 'bg-red-100 text-red-700',
                                        default     => 'bg-gray-100 text-gray-700',
                                    }; @endphp
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $sc }}">
                                        {{ $refund->status->label() }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-gray-500">{{ $refund->created_at->format('M j, Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endif

    {{-- Settlement tab --}}
    @if($tab === 'settlement')
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-800">Daily Settlement</h2>
            <a href="{{ route('finance.settlements') }}" class="text-sm text-indigo-600 hover:underline">View all</a>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6 mb-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Close Settlement for Date</h3>
            <div class="flex gap-3 items-end">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Date</label>
                    <input type="date" wire:model="settlementDate"
                           class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"/>
                </div>
                <button wire:click="closeSettlement"
                        wire:loading.attr="disabled"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                    <span wire:loading.remove wire:target="closeSettlement">Close Settlement</span>
                    <span wire:loading wire:target="closeSettlement">Processing…</span>
                </button>
            </div>
            @error('settlement') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="rounded-xl border border-gray-200 bg-white overflow-hidden">
            @if($settlements->isEmpty())
                <p class="px-6 py-8 text-center text-sm text-gray-500">No settlements yet.</p>
            @else
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left font-medium text-gray-500">Date</th>
                            <th class="px-6 py-3 text-left font-medium text-gray-500">Expected</th>
                            <th class="px-6 py-3 text-left font-medium text-gray-500">Actual</th>
                            <th class="px-6 py-3 text-left font-medium text-gray-500">Variance</th>
                            <th class="px-6 py-3 text-left font-medium text-gray-500">Status</th>
                            <th class="px-6 py-3 text-left font-medium text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($settlements as $settlement)
                            <tr>
                                <td class="px-6 py-4 text-gray-900">{{ $settlement->settlement_date->format('M j, Y') }}</td>
                                <td class="px-6 py-4 text-gray-900">{{ formatCurrency($settlement->expected_amount_cents) }}</td>
                                <td class="px-6 py-4 text-gray-900">{{ formatCurrency($settlement->net_amount_cents) }}</td>
                                <td class="px-6 py-4 {{ abs($settlement->variance_cents) > 1 ? 'text-red-600 font-semibold' : 'text-gray-600' }}">
                                    {{ formatCurrency($settlement->variance_cents) }}
                                </td>
                                <td class="px-6 py-4">
                                    @php $sc = match($settlement->status->value) {
                                        'OPEN'       => 'bg-yellow-100 text-yellow-700',
                                        'RECONCILED' => 'bg-green-100 text-green-700',
                                        'EXCEPTION'  => 'bg-red-100 text-red-700',
                                        default      => 'bg-gray-100 text-gray-700',
                                    }; @endphp
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $sc }}">
                                        {{ $settlement->status->label() }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <a href="{{ route('finance.settlements.show', $settlement) }}"
                                       class="text-indigo-600 hover:underline text-xs">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endif

    {{-- Exceptions tab --}}
    @if($tab === 'exceptions')
        <div class="mb-4">
            <h2 class="text-lg font-semibold text-gray-800">Open Settlement Exceptions</h2>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white overflow-hidden">
            @if($exceptions->isEmpty())
                <p class="px-6 py-8 text-center text-sm text-gray-500">No open exceptions.</p>
            @else
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left font-medium text-gray-500">Settlement</th>
                            <th class="px-6 py-3 text-left font-medium text-gray-500">Type</th>
                            <th class="px-6 py-3 text-left font-medium text-gray-500">Amount</th>
                            <th class="px-6 py-3 text-left font-medium text-gray-500">Status</th>
                            <th class="px-6 py-3 text-left font-medium text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($exceptions as $exception)
                            <tr>
                                <td class="px-6 py-4 text-gray-900">{{ $exception->settlement->settlement_date->format('M j, Y') }}</td>
                                <td class="px-6 py-4 text-gray-600">{{ $exception->exception_type->label() }}</td>
                                <td class="px-6 py-4 text-red-600 font-medium">{{ formatCurrency($exception->amount_cents) }}</td>
                                <td class="px-6 py-4">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold bg-red-100 text-red-700">
                                        {{ $exception->status->label() }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <a href="{{ route('finance.settlements.show', $exception->settlement) }}"
                                       class="text-indigo-600 hover:underline text-xs">Resolve</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endif

    {{-- Invoices tab --}}
    @if($tab === 'invoices')
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-800">Recent Invoices</h2>
            <div class="flex gap-3">
                <a href="{{ route('finance.invoices') }}" class="text-sm text-indigo-600 hover:underline">View all</a>
                <a href="{{ route('finance.invoices.create') }}"
                   class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700">
                    New Invoice
                </a>
            </div>
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
                                <td class="px-6 py-4">
                                    <a href="{{ route('finance.invoices.show', $invoice) }}"
                                       class="text-indigo-600 hover:underline text-xs">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endif
</div>
