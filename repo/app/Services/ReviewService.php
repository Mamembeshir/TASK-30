<?php

namespace App\Services;

use App\Enums\ReviewStatus;
use App\Enums\SignupStatus;
use App\Models\Trip;
use App\Models\TripReview;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ReviewService
{
    /**
     * REV-01: user must have a CONFIRMED signup on a trip that has already ended.
     * REV-02: one review per user per trip.
     */
    public function create(Trip $trip, User $user, int $rating, ?string $text): TripReview
    {
        $this->assertEligible($trip, $user);
        $this->assertRating($rating);

        return DB::transaction(function () use ($trip, $user, $rating, $text) {
            $review = TripReview::create([
                'trip_id'     => $trip->id,
                'user_id'     => $user->id,
                'rating'      => $rating,
                'review_text' => $text,
                'status'      => ReviewStatus::ACTIVE->value,
                'version'     => 1,
            ]);

            $this->recomputeAverageRating($trip);

            AuditService::record('review.created', 'TripReview', $review->id, null, [
                'trip_id' => $trip->id,
                'rating'  => $rating,
            ]);

            return $review;
        });
    }

    /**
     * Author may update their own ACTIVE review.
     */
    public function update(TripReview $review, User $author, int $rating, ?string $text): TripReview
    {
        if ($review->user_id !== $author->id) {
            throw new RuntimeException('You can only edit your own review.', 403);
        }

        if ($review->status !== ReviewStatus::ACTIVE) {
            throw new RuntimeException('Only ACTIVE reviews can be edited.', 422);
        }

        $this->assertRating($rating);

        return DB::transaction(function () use ($review, $rating, $text) {
            $before = ['rating' => $review->rating, 'review_text' => $review->review_text];

            $review->rating      = $rating;
            $review->review_text = $text;
            $review->saveWithLock();

            $this->recomputeAverageRating($review->trip);

            AuditService::record('review.updated', 'TripReview', $review->id, $before, [
                'rating'      => $rating,
                'review_text' => $text,
            ]);

            return $review->fresh();
        });
    }

    /**
     * Admin: flag a review — hides it from the trip page.
     */
    public function flag(TripReview $review): TripReview
    {
        if ($review->status !== ReviewStatus::ACTIVE) {
            throw new RuntimeException('Only ACTIVE reviews can be flagged.', 422);
        }

        return DB::transaction(function () use ($review) {
            $before         = ['status' => $review->status->value];
            $review->status = ReviewStatus::FLAGGED;
            $review->saveWithLock();

            $this->recomputeAverageRating($review->trip);

            AuditService::record('review.flagged', 'TripReview', $review->id, $before, [
                'status' => ReviewStatus::FLAGGED->value,
            ]);

            return $review->fresh();
        });
    }

    /**
     * Admin: permanently remove a review.
     */
    public function remove(TripReview $review): TripReview
    {
        if ($review->status === ReviewStatus::REMOVED) {
            throw new RuntimeException('Review is already removed.', 422);
        }

        return DB::transaction(function () use ($review) {
            $before         = ['status' => $review->status->value];
            $review->status = ReviewStatus::REMOVED;
            $review->saveWithLock();

            $this->recomputeAverageRating($review->trip);

            AuditService::record('review.removed', 'TripReview', $review->id, $before, [
                'status' => ReviewStatus::REMOVED->value,
            ]);

            return $review->fresh();
        });
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * REV-01 + REV-02 eligibility checks.
     */
    private function assertEligible(Trip $trip, User $user): void
    {
        // REV-01: must have a CONFIRMED signup
        $hasConfirmedSignup = $trip->signups()
            ->where('user_id', $user->id)
            ->where('status', SignupStatus::CONFIRMED->value)
            ->exists();

        if (! $hasConfirmedSignup) {
            throw new RuntimeException(
                'You can only review trips you have attended (must have a confirmed signup).',
                422
            );
        }

        // REV-01: trip must have ended
        if (! $trip->end_date->isPast()) {
            throw new RuntimeException(
                'You can only review a trip after it has ended.',
                422
            );
        }

        // REV-02: one review per user per trip
        $alreadyReviewed = TripReview::where('trip_id', $trip->id)
            ->where('user_id', $user->id)
            ->whereNot('status', ReviewStatus::REMOVED->value)
            ->exists();

        if ($alreadyReviewed) {
            throw new RuntimeException(
                'You have already submitted a review for this trip.',
                422
            );
        }
    }

    private function assertRating(int $rating): void
    {
        if ($rating < 1 || $rating > 5) {
            throw new RuntimeException('Rating must be between 1 and 5.', 422);
        }
    }

    /**
     * Recompute the trip's average_rating from all ACTIVE reviews,
     * then persist. Null when there are no active reviews.
     */
    public function recomputeAverageRating(Trip $trip): void
    {
        $avg = TripReview::where('trip_id', $trip->id)
            ->where('status', ReviewStatus::ACTIVE->value)
            ->avg('rating');

        // Use raw update to bypass optimistic locking — this is a derived value
        Trip::where('id', $trip->id)->update([
            'average_rating' => $avg !== null ? round((float) $avg, 2) : null,
        ]);
    }
}
