<?php

namespace App\Livewire\Reviews;

use App\Models\Trip;
use App\Models\TripReview;
use App\Services\ApiClient;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Standalone review form (also usable as an embedded modal).
 * Handles both create (no $review) and edit (existing $review).
 */
#[Layout('layouts.app')]
class ReviewForm extends Component
{
    public Trip $trip;
    public ?TripReview $review = null;

    public int    $rating     = 0;
    public string $reviewText = '';

    public function mount(Trip $trip, ?TripReview $review = null): void
    {
        $this->trip   = $trip;
        $this->review = $review;

        if ($review?->exists) {
            // Editing: only author can reach this
            if ($review->user_id !== Auth::id()) {
                abort(403);
            }
            $this->rating     = $review->rating;
            $this->reviewText = $review->review_text ?? '';
        }
    }

    public function setRating(int $rating): void
    {
        $this->rating = $rating;
    }

    public function submit(): void
    {
        $this->validate([
            'rating'     => 'required|integer|min:1|max:5',
            'reviewText' => 'nullable|string|max:2000',
        ]);

        if ($this->review?->exists) {
            $response = app(ApiClient::class)->put('/reviews/' . $this->review->id, [
                'rating'      => $this->rating,
                'review_text' => $this->reviewText ?: null,
            ]);

            if ($response->status() >= 400) {
                $this->addError('form', $response->json('message') ?? 'Failed to update review.');
                return;
            }

            $this->dispatch('notify', type: 'success', message: 'Review updated.');
        } else {
            $idempotencyKey = "review:{$this->trip->id}:" . Auth::id();

            $response = app(ApiClient::class)->post('/trips/' . $this->trip->id . '/reviews', [
                'rating'          => $this->rating,
                'review_text'     => $this->reviewText ?: null,
                'idempotency_key' => $idempotencyKey,
            ]);

            if ($response->status() >= 400) {
                $this->addError('form', $response->json('message') ?? 'Failed to submit review.');
                return;
            }

            $this->dispatch('notify', type: 'success', message: 'Review submitted. Thank you!');
        }

        $this->dispatch('review-saved');
        $this->redirectRoute('trips.show', $this->trip);
    }

    public function render()
    {
        return view('livewire.reviews.review-form');
    }
}
