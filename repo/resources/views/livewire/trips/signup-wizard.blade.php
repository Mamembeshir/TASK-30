<div>
    @if($step < 4)
        {{-- Progress bar --}}
        <div class="mb-8">
            <div class="flex items-center justify-between text-sm">
                @foreach(['Review', 'Details', 'Payment'] as $i => $label)
                    <div class="flex items-center gap-2">
                        <span class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold
                            {{ $step > $i + 1 ? 'bg-indigo-600 text-white' : ($step === $i + 1 ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-500') }}">
                            {{ $i + 1 }}
                        </span>
                        <span class="{{ $step === $i + 1 ? 'font-semibold text-indigo-600' : 'text-gray-400' }}">{{ $label }}</span>
                    </div>
                    @if($i < 2) <div class="flex-1 border-t border-gray-200 mx-3"></div> @endif
                @endforeach
            </div>
        </div>

        {{-- Hold countdown --}}
        <div class="mb-6 rounded-lg bg-amber-50 p-3 text-center text-sm text-amber-800"
             x-data="{ secs: {{ $holdSecondsRemaining }} }"
             x-init="setInterval(() => { if (secs > 0) secs-- }, 1000)">
            <span x-show="secs > 120">Hold expires in <span x-text="Math.floor(secs/60) + ':' + String(secs%60).padStart(2,'0')"></span></span>
            <span x-show="secs <= 120 && secs > 0" class="font-semibold text-red-700">
                Hurry! Hold expires in <span x-text="secs"></span> seconds
            </span>
            <span x-show="secs <= 0" class="font-semibold text-red-700">Your hold has expired.</span>
        </div>
    @endif

    {{-- Step 1: Review --}}
    @if($step === 1)
        <div class="rounded-xl border border-gray-200 bg-white p-6">
            <h2 class="mb-4 text-lg font-semibold">Review Your Trip</h2>
            <dl class="grid grid-cols-2 gap-4 text-sm">
                <div><dt class="text-gray-500">Trip</dt><dd class="font-medium">{{ $trip->title }}</dd></div>
                <div><dt class="text-gray-500">Destination</dt><dd class="font-medium">{{ $trip->destination }}</dd></div>
                <div><dt class="text-gray-500">Dates</dt><dd class="font-medium">{{ $trip->start_date->format('M j') }} – {{ $trip->end_date->format('M j, Y') }}</dd></div>
                <div><dt class="text-gray-500">Price</dt><dd class="font-medium">{{ $trip->formattedPrice() }}</dd></div>
            </dl>
        </div>
        <div class="mt-6 flex justify-end">
            <button wire:click="nextStep"
                    class="rounded-lg bg-indigo-600 px-6 py-2 font-medium text-white hover:bg-indigo-700">
                Continue
            </button>
        </div>
    @endif

    {{-- Step 2: Personal details --}}
    @if($step === 2)
        <div class="rounded-xl border border-gray-200 bg-white p-6 space-y-4">
            <h2 class="text-lg font-semibold">Emergency Contact</h2>
            <div>
                <label class="block text-sm font-medium text-gray-700">Contact Name <span class="text-red-500">*</span></label>
                <input wire:model="emergencyContactName" type="text"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                @error('emergencyContactName') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Contact Phone <span class="text-red-500">*</span></label>
                <input wire:model="emergencyContactPhone" type="tel"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                @error('emergencyContactPhone') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Dietary Requirements</label>
                <input wire:model="dietaryRequirements" type="text"
                       placeholder="None, vegetarian, etc."
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
            </div>
        </div>
        <div class="mt-6 flex justify-between">
            <button wire:click="prevStep"
                    class="rounded-lg border border-gray-300 px-6 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Back
            </button>
            <button wire:click="nextStep"
                    class="rounded-lg bg-indigo-600 px-6 py-2 font-medium text-white hover:bg-indigo-700">
                Continue
            </button>
        </div>
    @endif

    {{-- Step 3: Payment --}}
    @if($step === 3)
        <div class="rounded-xl border border-gray-200 bg-white p-6 space-y-4">
            <h2 class="text-lg font-semibold">Payment</h2>
            <p class="text-sm text-gray-500">Select your payment method. Finance staff will confirm receipt.</p>
            <div>
                <label class="block text-sm font-medium text-gray-700">Tender Type <span class="text-red-500">*</span></label>
                <select wire:model="tenderType"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    <option value="CASH">Cash</option>
                    <option value="CHECK">Check</option>
                    <option value="CARD_ON_FILE">Card on File</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Reference Number</label>
                <input wire:model="referenceNumber" type="text"
                       placeholder="Check # or transaction ID"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
            </div>
            <div class="flex items-center justify-between rounded-lg bg-gray-50 px-4 py-3">
                <span class="text-sm text-gray-700">Amount Due</span>
                <span class="text-lg font-bold text-gray-900">{{ $trip->formattedPrice() }}</span>
            </div>
            @error('payment') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            @error('hold') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div class="mt-6 flex justify-between">
            <button wire:click="prevStep"
                    class="rounded-lg border border-gray-300 px-6 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Back
            </button>
            <button wire:click="submitPayment"
                    wire:loading.attr="disabled"
                    class="rounded-lg bg-green-600 px-6 py-2 font-medium text-white hover:bg-green-700 disabled:opacity-50">
                <span wire:loading.remove>Confirm Booking</span>
                <span wire:loading>Processing…</span>
            </button>
        </div>
    @endif

    {{-- Step 4: Confirmation --}}
    @if($step === 4)
        <div class="rounded-xl border border-green-200 bg-green-50 p-8 text-center">
            <svg class="mx-auto mb-4 h-12 w-12 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            <h2 class="text-xl font-bold text-green-800">Booking Confirmed!</h2>
            <p class="mt-2 text-sm text-green-700">
                Your signup for <strong>{{ $trip->title }}</strong> has been recorded.
                Finance staff will confirm your payment shortly.
            </p>
            <div class="mt-6 flex justify-center gap-4">
                <a href="{{ route('my-signups') }}"
                   class="rounded-lg bg-indigo-600 px-6 py-2 font-medium text-white hover:bg-indigo-700">
                    View My Bookings
                </a>
                <a href="{{ route('trips.index') }}"
                   class="rounded-lg border border-gray-300 px-6 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Browse More Trips
                </a>
            </div>
        </div>
    @endif
</div>
