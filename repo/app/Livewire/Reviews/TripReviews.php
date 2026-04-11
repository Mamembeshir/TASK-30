<?php

namespace App\Livewire\Reviews;

use App\Enums\ReviewStatus;
use App\Enums\SignupStatus;
use App\Models\Trip;
use App\Models\TripReview;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Embeddable reviews list shown on the TripDetail page.
 * Displays only ACTIVE reviews. Shows "Write a Review" button when eligible.
 */
class TripReviews extends Component
{
    public Trip $trip;

    public bool $showForm = false;

    public function mount(Trip $trip): void
    {
        $this->trip = $trip;
    }

    public function getReviewsProperty()
    {
        return TripReview::with('user')
            ->where('trip_id', $this->trip->id)
            ->where('status', ReviewStatus::ACTIVE->value)
            ->latest()
            ->get();
    }

    /**
     * REV-01 eligibility: confirmed signup + trip has ended + no existing review.
     */
    public function getCanReviewProperty(): bool
    {
        if (! Auth::check()) {
            return false;
        }

        if (! $this->trip->end_date->isPast()) {
            return false;
        }

        $userId = Auth::id();

        $hasConfirmed = $this->trip->signups()
            ->where('user_id', $userId)
            ->where('status', SignupStatus::CONFIRMED->value)
            ->exists();

        if (! $hasConfirmed) {
            return false;
        }

        return ! TripReview::where('trip_id', $this->trip->id)
            ->where('user_id', $userId)
            ->whereNot('status', ReviewStatus::REMOVED->value)
            ->exists();
    }

    public function render()
    {
        return view('livewire.reviews.trip-reviews');
    }
}
