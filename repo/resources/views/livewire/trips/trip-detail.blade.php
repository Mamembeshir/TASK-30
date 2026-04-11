<div>
    {{-- Seat / status banner --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $trip->title }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ $trip->destination }} &middot; {{ $trip->specialty }}</p>
        </div>
        <span class="rounded-full px-3 py-1 text-sm font-semibold
            @if($trip->status->value === 'PUBLISHED') bg-green-100 text-green-700
            @elseif($trip->status->value === 'FULL') bg-yellow-100 text-yellow-700
            @else bg-gray-100 text-gray-600 @endif">
            {{ $trip->status->label() }}
        </span>
    </div>

    <div class="grid gap-8 lg:grid-cols-3">
        {{-- Main details --}}
        <div class="lg:col-span-2 space-y-6">
            <div class="rounded-xl border border-gray-200 bg-white p-6">
                <h2 class="mb-3 font-semibold text-gray-900">About This Trip</h2>
                <p class="text-gray-700 whitespace-pre-line">{{ $trip->description ?? 'No description provided.' }}</p>

                <dl class="mt-4 grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-gray-500">Dates</dt>
                        <dd class="font-medium text-gray-900">
                            {{ $trip->start_date->format('M j') }} – {{ $trip->end_date->format('M j, Y') }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Difficulty</dt>
                        <dd class="font-medium text-gray-900">{{ $trip->difficulty_level->label() }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Lead Physician</dt>
                        <dd class="font-medium text-gray-900">{{ $trip->doctor?->user?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Price</dt>
                        <dd class="font-medium text-gray-900">{{ $trip->formattedPrice() }}</dd>
                    </div>
                </dl>

                @if($trip->prerequisites)
                    <div class="mt-4">
                        <h3 class="text-sm font-medium text-gray-700">Prerequisites</h3>
                        <p class="mt-1 text-sm text-gray-600 whitespace-pre-line">{{ $trip->prerequisites }}</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Booking panel --}}
        <div>
            <div class="sticky top-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                {{-- Live seat count (updated via Echo) --}}
                <div class="mb-4 text-center">
                    <span class="text-3xl font-bold text-gray-900">{{ $trip->available_seats }}</span>
                    <span class="text-sm text-gray-500"> / {{ $trip->total_seats }} seats available</span>
                </div>

                @auth
                    @if($mySignup)
                        {{-- Already booked --}}
                        <div class="rounded-lg bg-green-50 p-4 text-center text-sm text-green-800">
                            <p class="font-medium">You're booked!</p>
                            <p class="mt-1">Status: {{ $mySignup->status->label() }}</p>
                            @if($mySignup->status->value === 'HOLD')
                                <p class="mt-1 text-xs font-medium"
                                   x-data="{ secs: {{ max(0, now()->diffInSeconds($mySignup->hold_expires_at, false)) }} }"
                                   x-init="setInterval(() => { if (secs > 0) secs-- }, 1000)"
                                   :class="secs < 120 ? 'text-red-600 animate-pulse' : 'text-amber-600'"
                                   x-text="'Hold expires in ' + Math.floor(secs/60) + ':' + String(secs%60).padStart(2,'0')">
                                </p>
                            @endif
                        </div>
                        <a href="{{ route('my-signups') }}"
                           class="mt-3 block w-full rounded-lg border border-gray-300 px-4 py-2 text-center text-sm font-medium text-gray-700 hover:bg-gray-50">
                            View My Bookings
                        </a>

                    @elseif($myWaitlistEntry)
                        {{-- On waitlist --}}
                        <div class="rounded-lg bg-blue-50 p-4 text-sm text-blue-800">
                            @if($myWaitlistEntry->status->value === 'OFFERED')
                                <p class="font-semibold text-amber-700">A seat is available for you!</p>
                                <p class="mt-1 text-xs">Offer expires at {{ $myWaitlistEntry->offer_expires_at?->format('H:i') }}</p>
                                <div class="mt-3 flex gap-2">
                                    <button wire:click="acceptOffer"
                                            class="flex-1 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">
                                        Accept
                                    </button>
                                    <button wire:click="declineOffer"
                                            class="flex-1 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                        Decline
                                    </button>
                                </div>
                            @else
                                <p class="font-medium">You're on the waitlist</p>
                                <p class="mt-1 text-xs">Position #{{ $myWaitlistEntry->position }}</p>
                            @endif
                        </div>

                    @elseif($trip->status->value === 'PUBLISHED')
                        <button wire:click="holdSeat"
                                wire:loading.attr="disabled"
                                class="w-full rounded-lg bg-indigo-600 px-4 py-3 font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                            <span wire:loading.remove>Book a Seat</span>
                            <span wire:loading>Reserving…</span>
                        </button>
                        @error('hold') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror

                    @elseif($trip->status->value === 'FULL')
                        <button wire:click="joinWaitlist"
                                wire:loading.attr="disabled"
                                class="w-full rounded-lg bg-amber-500 px-4 py-3 font-medium text-white hover:bg-amber-600 disabled:opacity-50">
                            <span wire:loading.remove>Join Waitlist</span>
                            <span wire:loading>Adding you…</span>
                        </button>
                        @error('waitlist') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror

                    @else
                        <p class="text-center text-sm text-gray-500">This trip is not currently accepting bookings.</p>
                    @endif
                @else
                    <a href="{{ route('login') }}"
                       class="block w-full rounded-lg bg-indigo-600 px-4 py-3 text-center font-medium text-white hover:bg-indigo-700">
                        Log in to Book
                    </a>
                @endauth
            </div>
        </div>
    </div>

    {{-- Reviews section --}}
    <div class="mt-8 lg:max-w-3xl">
        <livewire:reviews.trip-reviews :trip="$trip" />
    </div>
</div>
