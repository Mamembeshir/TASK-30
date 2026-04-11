<?php

namespace App\Strategies;

use App\Enums\TripStatus;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * SRCH-07: PUBLISHED trips ordered by start_date ascending (closest upcoming first).
 * Label: "Coming Up Soon"
 */
class UpcomingSoonest implements RecommendationStrategy
{
    public function key(): string
    {
        return 'upcoming_soonest';
    }

    public function label(): string
    {
        return 'Coming Up Soon';
    }

    public function recommend(User $user, int $limit = 5): Collection
    {
        return Trip::where('status', TripStatus::PUBLISHED->value)
            ->where('start_date', '>', now())
            ->orderBy('start_date')
            ->limit($limit)
            ->get();
    }
}
