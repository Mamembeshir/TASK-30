<div class="max-w-lg mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Purchase Membership</h1>

    @if($error)
        <div class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-700">{{ $error }}</div>
    @endif

    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <div class="mb-6">
            <h2 class="text-lg font-semibold text-gray-900">{{ $plan->name }}</h2>
            <p class="mt-1 text-sm text-gray-500">{{ $plan->tier->label() }} &middot; {{ $plan->duration_months }} month(s)</p>
            @if($plan->description)
                <p class="mt-2 text-sm text-gray-600">{{ $plan->description }}</p>
            @endif
        </div>

        <div class="border-t border-gray-100 pt-4 mb-6">
            <div class="flex justify-between text-sm">
                <span class="text-gray-600">Total</span>
                <span class="text-xl font-bold text-gray-900">{{ $plan->formattedPrice() }}</span>
            </div>
        </div>

        @if(! $confirmed)
            <div class="flex gap-3">
                <a href="{{ route('membership.index') }}"
                   class="flex-1 rounded-lg border border-gray-300 px-4 py-2 text-center text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Back
                </a>
                <button wire:click="confirm"
                        class="flex-1 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    Confirm Order
                </button>
            </div>
        @else
            <div class="rounded-lg bg-yellow-50 p-4 mb-4 text-sm text-yellow-800">
                Payment processing is handled in a later step. Clicking "Place Order" will create a pending order.
            </div>
            <button wire:click="submit"
                    wire:loading.attr="disabled"
                    class="w-full rounded-lg bg-green-600 px-4 py-2 font-medium text-white hover:bg-green-700 disabled:opacity-50">
                <span wire:loading.remove>Place Order</span>
                <span wire:loading>Processing…</span>
            </button>
        @endif
    </div>
</div>
