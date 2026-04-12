<?php

namespace App\Livewire\Reviews;

use App\Enums\ReviewStatus;
use App\Enums\UserRole;
use App\Models\TripReview;
use App\Services\ApiClient;
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

    public function flag(string $reviewId): void
    {
        $review = TripReview::findOrFail($reviewId);

        $response = app(ApiClient::class)->post('/admin/reviews/' . $review->id . '/flag', [
            'idempotency_key' => 'review.flag.' . $review->id,
        ]);

        if ($response->status() >= 400) {
            $this->addError('action', $response->json('message') ?? 'Failed to flag review.');
            return;
        }

        $this->dispatch('notify', type: 'warning', message: 'Review flagged.');
    }

    public function remove(string $reviewId): void
    {
        $review = TripReview::findOrFail($reviewId);

        $response = app(ApiClient::class)->post('/admin/reviews/' . $review->id . '/remove', [
            'idempotency_key' => 'review.remove.' . $review->id,
        ]);

        if ($response->status() >= 400) {
            $this->addError('action', $response->json('message') ?? 'Failed to remove review.');
            return;
        }

        $this->dispatch('notify', type: 'success', message: 'Review removed.');
    }

    public function restore(string $reviewId): void
    {
        $review = TripReview::findOrFail($reviewId);

        $response = app(ApiClient::class)->post('/admin/reviews/' . $review->id . '/restore');

        if ($response->status() >= 400) {
            $this->addError('action', $response->json('message') ?? 'Failed to restore review.');
            return;
        }

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
