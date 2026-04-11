<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">
            {{ $review?->exists ? 'Edit Your Review' : 'Write a Review' }}
        </h1>
        <p class="mt-1 text-sm text-gray-500">{{ $trip->title }}</p>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-6 space-y-6">

        @error('form')
            <div class="rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ $message }}</div>
        @enderror

        {{-- Star selector --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Rating <span class="text-red-500">*</span>
            </label>
            <div class="flex gap-1"
                 x-data="{ hovered: 0, selected: @entangle('rating') }">
                @for($i = 1; $i <= 5; $i++)
                    <button type="button"
                            wire:click="setRating({{ $i }})"
                            x-on:mouseenter="hovered = {{ $i }}"
                            x-on:mouseleave="hovered = 0"
                            class="text-3xl leading-none transition-colors focus:outline-none"
                            :class="(hovered || selected) >= {{ $i }} ? 'text-amber-400' : 'text-gray-300'"
                            aria-label="{{ $i }} star{{ $i > 1 ? 's' : '' }}">
                        ★
                    </button>
                @endfor
                <span class="ml-3 self-center text-sm text-gray-500"
                      x-text="selected ? selected + (selected === 1 ? ' star' : ' stars') : 'Select a rating'">
                </span>
            </div>
            @error('rating') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        {{-- Review text --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Review
                <span class="text-gray-400 font-normal">(optional)</span>
            </label>
            <div x-data="{ chars: {{ strlen($reviewText) }} }">
                <textarea wire:model="reviewText"
                          x-on:input="chars = $event.target.value.length"
                          rows="5"
                          maxlength="2000"
                          placeholder="Share your experience…"
                          class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm resize-none"></textarea>
                <p class="mt-1 text-right text-xs"
                   :class="chars > 1900 ? 'text-red-500' : 'text-gray-400'">
                    <span x-text="chars"></span>/2000
                </p>
            </div>
            @error('reviewText') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        {{-- Actions --}}
        <div class="flex items-center justify-between pt-2">
            <a href="{{ route('trips.show', $trip) }}"
               class="text-sm text-gray-500 hover:text-gray-700">
                &larr; Cancel
            </a>
            <button wire:click="submit"
                    wire:loading.attr="disabled"
                    class="rounded-lg bg-indigo-600 px-6 py-2 font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                <span wire:loading.remove>{{ $review?->exists ? 'Update Review' : 'Submit Review' }}</span>
                <span wire:loading>Saving…</span>
            </button>
        </div>
    </div>
</div>
