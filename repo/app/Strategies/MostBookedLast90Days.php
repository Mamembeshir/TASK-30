<?php

namespace App\Strategies;

use App\Enums\TripStatus;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * SRCH-07: Return the most-booked PUBLISHED trips created in the last 90 days.
 * Label: "Popular This Quarter"
 */
class MostBookedLast90Days implements RecommendationStrategy
{
    public function key(): string
    {
        return 'most_booked_90d';
    }

    public function label(): string
    {
        return 'Popular This Quarter';
    }

    public function recommend(User $user, int $limit = 5): Collection
    {
        return Trip::where('status', TripStatus::PUBLISHED->value)
            ->where('created_at', '>=', now()->subDays(90))
            ->orderByDesc('booking_count')
            ->limit($limit)
            ->get();
    }
}
