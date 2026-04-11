<div class="mt-8">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold text-gray-900">
            Reviews
            @if($trip->average_rating)
                <span class="ml-2 text-sm font-normal text-gray-500">
                    &mdash;
                    <span class="text-amber-500">&#9733;</span>
                    {{ number_format($trip->average_rating, 1) }}
                    ({{ $this->reviews->count() }})
                </span>
            @endif
        </h2>

        @if($this->canReview)
            <a href="{{ route('trips.reviews.create', $trip) }}"
               class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                Write a Review
            </a>
        @endif
    </div>

    @if($this->reviews->isEmpty())
        <p class="text-sm text-gray-500 py-6 text-center">
            No reviews yet.
            @if($this->canReview)
                Be the first to leave one!
            @endif
        </p>
    @else
        <div class="space-y-4">
            @foreach($this->reviews as $review)
                <div class="rounded-xl border border-gray-200 bg-white p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-medium text-gray-900">{{ $review->user?->name ?? 'Anonymous' }}</p>
                            <p class="text-xs text-gray-400 mt-0.5">{{ $review->created_at->format('M j, Y') }}</p>
                        </div>
                        {{-- Star rating --}}
                        <div class="flex shrink-0 text-amber-400 text-lg leading-none">
                            @for($i = 1; $i <= 5; $i++)
                                <span>{{ $i <= $review->rating ? '★' : '☆' }}</span>
                            @endfor
                        </div>
                    </div>

                    @if($review->review_text)
                        <p class="mt-3 text-sm text-gray-700 whitespace-pre-line">{{ $review->review_text }}</p>
                    @endif

                    @auth
                        @if(auth()->id() === $review->user_id)
                            <div class="mt-3 flex justify-end">
                                <a href="{{ route('trips.reviews.edit', ['trip' => $trip, 'review' => $review]) }}"
                                   class="text-xs text-indigo-600 hover:underline">Edit</a>
                            </div>
                        @endif
                    @endauth
                </div>
            @endforeach
        </div>
    @endif
</div>
