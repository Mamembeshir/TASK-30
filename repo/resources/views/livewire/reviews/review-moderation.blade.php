<div>
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Review Moderation</h1>
        <div>
            <label class="text-sm text-gray-600 mr-2">Status</label>
            <select wire:model.live="filterStatus"
                    class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">All</option>
                @foreach($statuses as $status)
                    <option value="{{ $status->value }}">{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @error('action')
        <div class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ $message }}</div>
    @enderror

    @if($reviews->isEmpty())
        <p class="py-12 text-center text-gray-500">No reviews found.</p>
    @else
        <div class="space-y-3">
            @foreach($reviews as $review)
                <div class="rounded-xl border bg-white p-5
                    @if($review->status->value === 'FLAGGED') border-amber-300 bg-amber-50
                    @elseif($review->status->value === 'REMOVED') border-gray-200 opacity-60
                    @else border-gray-200 @endif">

                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2 text-sm">
                                <span class="font-medium text-gray-900">{{ $review->user?->name }}</span>
                                <span class="text-gray-400">&rarr;</span>
                                <a href="{{ route('trips.show', $review->trip) }}"
                                   class="text-indigo-600 hover:underline truncate">
                                    {{ $review->trip->title }}
                                </a>
                                <span class="text-amber-400">
                                    @for($i = 1; $i <= 5; $i++){{ $i <= $review->rating ? '★' : '☆' }}@endfor
                                </span>
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium
                                    @if($review->status->value === 'ACTIVE') bg-green-100 text-green-700
                                    @elseif($review->status->value === 'FLAGGED') bg-amber-100 text-amber-700
                                    @else bg-gray-100 text-gray-500 @endif">
                                    {{ $review->status->label() }}
                                </span>
                                <span class="text-gray-400 text-xs">{{ $review->created_at->format('M j, Y') }}</span>
                            </div>

                            @if($review->review_text)
                                <p class="mt-2 text-sm text-gray-700 line-clamp-3">{{ $review->review_text }}</p>
                            @endif
                        </div>

                        {{-- Actions --}}
                        <div class="flex shrink-0 gap-2">
                            @if($review->status->value === 'ACTIVE')
                                <button wire:click="flag('{{ $review->id }}')"
                                        wire:confirm="Flag this review? It will be hidden from the trip page."
                                        class="rounded px-3 py-1 text-xs font-medium bg-amber-100 text-amber-700 hover:bg-amber-200">
                                    Flag
                                </button>
                                <button wire:click="remove('{{ $review->id }}')"
                                        wire:confirm="Permanently remove this review?"
                                        class="rounded px-3 py-1 text-xs font-medium bg-red-100 text-red-700 hover:bg-red-200">
                                    Remove
                                </button>
                            @elseif($review->status->value === 'FLAGGED')
                                <button wire:click="restore('{{ $review->id }}')"
                                        class="rounded px-3 py-1 text-xs font-medium bg-green-100 text-green-700 hover:bg-green-200">
                                    Restore
                                </button>
                                <button wire:click="remove('{{ $review->id }}')"
                                        wire:confirm="Permanently remove this review?"
                                        class="rounded px-3 py-1 text-xs font-medium bg-red-100 text-red-700 hover:bg-red-200">
                                    Remove
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6">{{ $reviews->links() }}</div>
    @endif
</div>
