<div>
    <x-page-header title="Record Payment"
        :breadcrumbs="[['label'=>'Dashboard','route'=>'dashboard'],['label'=>'Finance','route'=>'finance.index'],['label'=>'Payments','route'=>'finance.payments'],['label'=>'Record']]"/>

    @if($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-700">
            @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <div class="max-w-lg">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm space-y-5">

            {{-- Member search --}}
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

            {{-- Tender type --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tender Type</label>
                <select wire:model="tenderType"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">Select tender type…</option>
                    @foreach($tenderTypes as $type)
                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                    @endforeach
                </select>
                @error('tenderType') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Amount --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Amount ($)</label>
                <input type="number" step="0.01" min="0.01"
                       wire:model="amountInput"
                       placeholder="0.00"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"/>
                @error('amountInput') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Reference --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Reference (optional)</label>
                <input type="text"
                       wire:model="referenceNumber"
                       placeholder="Check #, wire ref, etc."
                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"/>
                @error('referenceNumber') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex gap-3 pt-2">
                <a href="{{ route('finance.payments') }}"
                   class="flex-1 rounded-lg border border-gray-300 px-4 py-2 text-center text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Cancel
                </a>
                <button wire:click="submit"
                        wire:loading.attr="disabled"
                        class="flex-1 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                    <span wire:loading.remove wire:target="submit">Record Payment</span>
                    <span wire:loading wire:target="submit">Recording…</span>
                </button>
            </div>
        </div>
    </div>
</div>
