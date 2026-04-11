<div class="max-w-lg mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Request Refund</h1>

    @if($error)
        <div class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-700">{{ $error }}</div>
    @endif

    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <div class="mb-6 rounded-lg bg-gray-50 p-4">
            <p class="text-sm font-medium text-gray-700">Order: {{ $order->plan->name }}</p>
            <p class="text-sm text-gray-500">Amount: {{ $order->formattedAmount() }}</p>
        </div>

        <form wire:submit.prevent="submit" class="space-y-5">
            {{-- Refund type --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Refund Type</label>
                <div class="flex gap-4">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="radio" wire:model.live="refundType" value="FULL" class="text-indigo-600">
                        Full Refund
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="radio" wire:model.live="refundType" value="PARTIAL" class="text-indigo-600">
                        Partial Refund
                    </label>
                </div>
                @error('refundType') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Partial amount --}}
            @if($refundType === 'PARTIAL')
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Refund Amount ($)</label>
                    <input type="number" step="0.01" min="0.01"
                           wire:model="amountInput"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                           placeholder="0.00">
                    @error('amountInput') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            @endif

            {{-- Reason --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Reason <span class="text-gray-400">(min 10 characters)</span></label>
                <textarea wire:model="reason" rows="4"
                          class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                          placeholder="Please describe the reason for your refund request…"></textarea>
                @error('reason') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex gap-3 pt-2">
                <a href="{{ route('membership.my') }}"
                   class="flex-1 rounded-lg border border-gray-300 px-4 py-2 text-center text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Cancel
                </a>
                <button type="submit"
                        wire:loading.attr="disabled"
                        class="flex-1 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50">
                    <span wire:loading.remove>Submit Request</span>
                    <span wire:loading>Submitting…</span>
                </button>
            </div>
        </form>
    </div>
</div>
