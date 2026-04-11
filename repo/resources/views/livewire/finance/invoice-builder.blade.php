<div>
    <x-page-header :title="$invoice ? 'Edit Invoice' : 'New Invoice'"
        :breadcrumbs="[['label'=>'Dashboard','route'=>'dashboard'],['label'=>'Finance','route'=>'finance.index'],['label'=>'Invoices','route'=>'finance.invoices'],['label'=>$invoice?->invoice_number ?? 'New']]"/>

    @if($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-700">
            @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">

            {{-- Step 1: Select member & create invoice --}}
            @if(!$invoice)
                <div class="rounded-xl border border-gray-200 bg-white p-6">
                    <h2 class="text-sm font-semibold text-gray-700 mb-4">Step 1: Select Member</h2>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Member</label>
                            <input type="text"
                                   wire:model.live="userSearch"
                                   placeholder="Search by email or username…"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"/>
                            @if(!empty($userResults))
                                <ul class="mt-1 rounded-lg border border-gray-200 bg-white shadow-lg divide-y divide-gray-100 text-sm">
                                    @foreach($userResults as $result)
                                        <li>
                                            <button type="button"
                                                    wire:click="selectUser('{{ $result['id'] }}')"
                                                    class="w-full px-4 py-2 text-left hover:bg-gray-50">
                                                {{ $result['label'] }}
                                            </button>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                            @error('selectedUserId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <button wire:click="createInvoice"
                                wire:loading.attr="disabled"
                                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                            <span wire:loading.remove wire:target="createInvoice">Create Invoice</span>
                            <span wire:loading wire:target="createInvoice">Creating…</span>
                        </button>
                    </div>
                </div>
            @else
                {{-- Invoice header --}}
                <div class="rounded-xl border border-gray-200 bg-white p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900 font-mono">{{ $invoice->invoice_number }}</h2>
                            <p class="text-sm text-gray-500 mt-1">{{ $invoice->user->email }}</p>
                        </div>
                        <span class="rounded-full px-3 py-1 text-xs font-semibold bg-gray-100 text-gray-700">
                            {{ $invoice->status->label() }}
                        </span>
                    </div>
                </div>

                {{-- Step 2: Line items --}}
                <div class="rounded-xl border border-gray-200 bg-white p-6">
                    <h2 class="text-sm font-semibold text-gray-700 mb-4">Line Items</h2>

                    @if($invoice->lineItems->isNotEmpty())
                        <table class="min-w-full divide-y divide-gray-200 text-sm mb-4">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left font-medium text-gray-500">Description</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-500">Type</th>
                                    <th class="px-4 py-2 text-right font-medium text-gray-500">Amount</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($invoice->lineItems as $item)
                                    <tr>
                                        <td class="px-4 py-3 text-gray-900">{{ $item->description }}</td>
                                        <td class="px-4 py-3 text-gray-600">{{ $item->line_type->label() }}</td>
                                        <td class="px-4 py-3 text-right text-gray-900">{{ formatCurrency($item->amount_cents) }}</td>
                                    </tr>
                                @endforeach
                                <tr class="bg-gray-50 font-semibold">
                                    <td colspan="2" class="px-4 py-3 text-right text-gray-700">Total</td>
                                    <td class="px-4 py-3 text-right text-gray-900">{{ $invoice->formattedTotal() }}</td>
                                </tr>
                            </tbody>
                        </table>
                    @endif

                    @if($invoice->status->value === 'DRAFT')
                        <div class="border-t border-gray-200 pt-4 space-y-3">
                            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Add Line Item</h3>
                            @error('line') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                            <div class="grid grid-cols-2 gap-3">
                                <div class="col-span-2">
                                    <label class="block text-xs text-gray-500 mb-1">Description</label>
                                    <input type="text"
                                           wire:model="lineDescription"
                                           placeholder="Service description…"
                                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"/>
                                    @error('lineDescription') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Amount ($)</label>
                                    <input type="number" step="0.01" min="0.01"
                                           wire:model="lineAmount"
                                           placeholder="0.00"
                                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"/>
                                    @error('lineAmount') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Type</label>
                                    <select wire:model="lineType"
                                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                        <option value="">Select type…</option>
                                        @foreach($lineTypes as $type)
                                            <option value="{{ $type->value }}">{{ $type->label() }}</option>
                                        @endforeach
                                    </select>
                                    @error('lineType') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                            </div>
                            <button wire:click="addLine"
                                    wire:loading.attr="disabled"
                                    class="rounded-lg bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-900 disabled:opacity-50">
                                <span wire:loading.remove wire:target="addLine">Add Line</span>
                                <span wire:loading wire:target="addLine">Adding…</span>
                            </button>
                        </div>
                    @endif
                </div>
            @endif
        </div>

        {{-- Sidebar actions --}}
        @if($invoice)
            <div class="space-y-4">
                @if($invoice->status->value === 'DRAFT')
                    <div class="rounded-xl border border-gray-200 bg-white p-6">
                        <h2 class="text-sm font-semibold text-gray-700 mb-3">Issue Invoice</h2>
                        <p class="text-xs text-gray-500 mb-3">Once issued, no more line items can be added.</p>
                        @error('issue') <p class="mb-2 text-xs text-red-600">{{ $message }}</p> @enderror
                        <button wire:click="issue"
                                wire:loading.attr="disabled"
                                @if($invoice->lineItems->isEmpty()) disabled @endif
                                class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                            <span wire:loading.remove wire:target="issue">Issue Invoice</span>
                            <span wire:loading wire:target="issue">Issuing…</span>
                        </button>
                        @if($invoice->lineItems->isEmpty())
                            <p class="mt-2 text-xs text-gray-400">Add at least one line item first.</p>
                        @endif
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
