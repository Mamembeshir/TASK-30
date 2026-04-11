<div>
    <x-page-header title="Export Statement"
        :breadcrumbs="[['label'=>'Dashboard','route'=>'dashboard'],['label'=>'Finance','route'=>'finance.index'],['label'=>'Export Statement']]"/>

    @if($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-700">
            @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <div class="max-w-lg">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-700 mb-4">Select Settlement</h2>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Settlement</label>
                    <select wire:model="settlementId"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">Choose a settlement…</option>
                        @foreach($settlements as $settlement)
                            <option value="{{ $settlement->id }}">
                                {{ $settlement->settlement_date->format('M j, Y') }}
                                — {{ $settlement->status->label() }}
                                ({{ formatCurrency($settlement->net_amount_cents) }})
                            </option>
                        @endforeach
                    </select>
                    @error('settlementId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <button wire:click="download"
                        wire:loading.attr="disabled"
                        class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                    <span wire:loading.remove wire:target="download">Download CSV</span>
                    <span wire:loading wire:target="download">Preparing…</span>
                </button>
                @error('download') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>
</div>
