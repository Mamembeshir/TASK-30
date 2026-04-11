<div>
    <x-page-header :title="'Invoice ' . $invoice->invoice_number"
        :breadcrumbs="[['label'=>'Dashboard','route'=>'dashboard'],['label'=>'Finance','route'=>'finance.index'],['label'=>'Invoices','route'=>'finance.invoices'],['label'=>$invoice->invoice_number]]"/>

    @if($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-700">
            @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Invoice details --}}
        <div class="lg:col-span-2 space-y-6">
            <div class="rounded-xl border border-gray-200 bg-white p-6">
                <div class="flex items-start justify-between mb-6">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 font-mono">{{ $invoice->invoice_number }}</h2>
                        <p class="text-sm text-gray-500 mt-1">{{ $invoice->user->email }}</p>
                    </div>
                    @php $sc = match($invoice->status->value) {
                        'DRAFT'  => 'bg-gray-100 text-gray-700',
                        'ISSUED' => 'bg-blue-100 text-blue-700',
                        'PAID'   => 'bg-green-100 text-green-700',
                        'VOIDED' => 'bg-red-100 text-red-700',
                        default  => 'bg-gray-100 text-gray-700',
                    }; @endphp
                    <span class="rounded-full px-3 py-1 text-sm font-semibold {{ $sc }}">
                        {{ $invoice->status->label() }}
                    </span>
                </div>

                <dl class="grid grid-cols-2 gap-4 text-sm mb-6">
                    <div>
                        <dt class="text-gray-500">Issued</dt>
                        <dd class="text-gray-900">{{ $invoice->issued_at?->format('M j, Y') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Due</dt>
                        <dd class="text-gray-900">{{ $invoice->due_date?->format('M j, Y') ?? '—' }}</dd>
                    </div>
                    @if($invoice->notes)
                        <div class="col-span-2">
                            <dt class="text-gray-500">Notes</dt>
                            <dd class="text-gray-900">{{ $invoice->notes }}</dd>
                        </div>
                    @endif
                </dl>

                {{-- Line items --}}
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium text-gray-500">Description</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-500">Type</th>
                            <th class="px-4 py-2 text-right font-medium text-gray-500">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($lineItems as $item)
                            <tr>
                                <td class="px-4 py-3 text-gray-900">{{ $item->description }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $item->line_type->label() }}</td>
                                <td class="px-4 py-3 text-right text-gray-900">{{ formatCurrency($item->amount_cents) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-6 text-center text-sm text-gray-500">No line items.</td>
                            </tr>
                        @endforelse
                        <tr class="bg-gray-50 font-semibold">
                            <td colspan="2" class="px-4 py-3 text-right text-gray-700">Total</td>
                            <td class="px-4 py-3 text-right text-gray-900">{{ $invoice->formattedTotal() }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Actions --}}
        <div class="space-y-4">
            @if($invoice->status->value === 'ISSUED')
                <div class="rounded-xl border border-gray-200 bg-white p-6">
                    <h2 class="text-sm font-semibold text-gray-700 mb-3">Mark as Paid</h2>
                    @error('paid') <p class="mb-2 text-xs text-red-600">{{ $message }}</p> @enderror
                    <button wire:click="markPaid"
                            wire:loading.attr="disabled"
                            class="w-full rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50">
                        <span wire:loading.remove wire:target="markPaid">Mark Paid</span>
                        <span wire:loading wire:target="markPaid">Updating…</span>
                    </button>
                </div>
            @endif

            @if(in_array($invoice->status->value, ['DRAFT', 'ISSUED']))
                <div class="rounded-xl border border-gray-200 bg-white p-6">
                    <h2 class="text-sm font-semibold text-gray-700 mb-3">Void Invoice</h2>
                    @error('void') <p class="mb-2 text-xs text-red-600">{{ $message }}</p> @enderror
                    @if(!$showVoidConfirm)
                        <button wire:click="$set('showVoidConfirm', true)"
                                class="w-full rounded-lg border border-red-300 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50">
                            Void Invoice
                        </button>
                    @else
                        <p class="text-sm text-gray-600 mb-3">This cannot be undone. Confirm?</p>
                        <div class="flex gap-2">
                            <button wire:click="$set('showVoidConfirm', false)"
                                    class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                Cancel
                            </button>
                            <button wire:click="void"
                                    wire:loading.attr="disabled"
                                    class="flex-1 rounded-lg bg-red-600 px-3 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50">
                                <span wire:loading.remove wire:target="void">Void</span>
                                <span wire:loading wire:target="void">Voiding…</span>
                            </button>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
