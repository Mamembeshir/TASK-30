<?php

namespace App\Livewire\Reviews;

use App\Enums\ReviewStatus;
use App\Enums\UserRole;
use App\Models\TripReview;
use App\Services\ReviewService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Admin-only review moderation panel.
 */
#[Layout('layouts.app')]
class ReviewModeration extends Component
{
    use WithPagination;

    #[Url]
    public string $filterStatus = '';

    public function mount(): void
    {
        if (! Auth::check() || ! Auth::user()->hasRole(UserRole::ADMIN)) {
            abort(403, 'Access restricted to administrators.');
        }
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function flag(string $reviewId, ReviewService $reviewService): void
    {
        $review = TripReview::findOrFail($reviewId);

        try {
            $reviewService->flag($review);
            $this->dispatch('notify', type: 'warning', message: 'Review flagged.');
        } catch (\RuntimeException $e) {
            $this->addError('action', $e->getMessage());
        }
    }

    public function remove(string $reviewId, ReviewService $reviewService): void
    {
        $review = TripReview::findOrFail($reviewId);

        try {
            $reviewService->remove($review);
            $this->dispatch('notify', type: 'success', message: 'Review removed.');
        } catch (\RuntimeException $e) {
            $this->addError('action', $e->getMessage());
        }
    }

    public function restore(string $reviewId, ReviewService $reviewService): void
    {
        $review = TripReview::findOrFail($reviewId);

        if ($review->status !== ReviewStatus::FLAGGED) {
            $this->addError('action', 'Only flagged reviews can be restored.');
            return;
        }

        $before         = ['status' => $review->status->value];
        $review->status = ReviewStatus::ACTIVE;
        $review->saveWithLock();
        $reviewService->recomputeAverageRating($review->trip);

        \App\Services\AuditService::record('review.restored', 'TripReview', $review->id, $before, [
            'status' => ReviewStatus::ACTIVE->value,
        ]);

        $this->dispatch('notify', type: 'success', message: 'Review restored.');
    }

    public function render()
    {
        $query = TripReview::with(['trip', 'user'])
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->latest();

        return view('livewire.reviews.review-moderation', [
            'reviews'  => $query->paginate(20),
            'statuses' => ReviewStatus::cases(),
        ]);
    }
}
