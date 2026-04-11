<div class="space-y-8">
    {{-- Waitlist offers --}}
    @if($waitlistEntries->where('status.value', 'OFFERED')->isNotEmpty())
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-5">
            <h2 class="mb-3 font-semibold text-amber-800">Seat Offers — Act Now!</h2>
            @foreach($waitlistEntries->where('status.value', 'OFFERED') as $entry)
                <div class="flex items-center justify-between rounded-lg bg-white px-4 py-3 shadow-sm mb-2">
                    <div>
                        <p class="font-medium text-gray-900">{{ $entry->trip->title }}</p>
                        <p class="text-xs text-amber-600">
                            Expires at {{ $entry->offer_expires_at?->format('H:i') }}
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <button wire:click="acceptOffer('{{ $entry->id }}')"
                                class="rounded-lg bg-green-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-green-700">
                            Accept
                        </button>
                        <button wire:click="declineOffer('{{ $entry->id }}')"
                                class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Decline
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Active signups --}}
    <div>
        <h2 class="mb-4 text-lg font-semibold text-gray-900">My Bookings</h2>
        @if($signups->isEmpty())
            <p class="text-sm text-gray-500">You have no bookings yet. <a href="{{ route('trips.index') }}" class="text-indigo-600 hover:underline">Browse trips</a>.</p>
        @else
            <div class="space-y-3">
                @foreach($signups as $signup)
                    <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-5 py-4">
                        <div>
                            <a href="{{ route('trips.show', $signup->trip) }}"
                               class="font-medium text-gray-900 hover:text-indigo-600">
                                {{ $signup->trip->title }}
                            </a>
                            <p class="text-sm text-gray-500">
                                {{ $signup->trip->start_date->format('M j, Y') }} &middot; {{ $signup->trip->destination }}
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="rounded-full px-3 py-1 text-xs font-semibold
                                @if($signup->status->value === 'CONFIRMED') bg-green-100 text-green-700
                                @elseif($signup->status->value === 'HOLD') bg-amber-100 text-amber-700
                                @elseif($signup->status->value === 'CANCELLED') bg-red-100 text-red-700
                                @else bg-gray-100 text-gray-600 @endif">
                                {{ $signup->status->label() }}
                            </span>
                            @if($signup->status->value === 'HOLD')
                                <button wire:click="cancelSignup('{{ $signup->id }}')"
                                        wire:confirm="Cancel this hold?"
                                        class="text-xs text-red-600 hover:underline">
                                    Cancel Hold
                                </button>
                            @elseif($signup->status->value === 'CONFIRMED')
                                <button wire:click="cancelSignup('{{ $signup->id }}')"
                                        wire:confirm="Cancel this confirmed booking? This may result in a refund request."
                                        class="text-xs text-red-600 hover:underline">
                                    Cancel
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Waitlist --}}
    @if($waitlistEntries->isNotEmpty())
        <div>
            <h2 class="mb-4 text-lg font-semibold text-gray-900">Waitlist</h2>
            <div class="space-y-3">
                @foreach($waitlistEntries as $entry)
                    @if($entry->status->value !== 'OFFERED')
                        <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-5 py-4">
                            <div>
                                <p class="font-medium text-gray-900">{{ $entry->trip->title }}</p>
                                <p class="text-sm text-gray-500">Position #{{ $entry->position }}</p>
                            </div>
                            <span class="rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-700">
                                {{ $entry->status->label() }}
                            </span>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    @endif

    @error('cancel') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
    @error('offer')  <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
</div>
