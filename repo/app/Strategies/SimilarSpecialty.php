<?php

namespace App\Strategies;

use App\Enums\SignupStatus;
use App\Enums\TripStatus;
use App\Models\Trip;
use App\Models\TripSignup;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * SRCH-07: Recommend trips that share a specialty with the user's past confirmed signups.
 * Returns empty collection if user has no confirmed signups.
 * Label: "Similar to Your Interests"
 */
class SimilarSpecialty implements RecommendationStrategy
{
    public function key(): string
    {
        return 'similar_specialty';
    }

    public function label(): string
    {
        return 'Similar to Your Interests';
    }

    public function recommend(User $user, int $limit = 5): Collection
    {
        // Gather distinct specialties from user's past signups (via trip relationship)
        $specialties = TripSignup::where('user_id', $user->id)
            ->whereIn('status', [
                SignupStatus::CONFIRMED->value,
                SignupStatus::CANCELLED->value, // include cancelled to surface preferences
            ])
            ->with('trip:id,specialty')
            ->get()
            ->pluck('trip.specialty')
            ->filter()
            ->unique()
            ->values();

        if ($specialties->isEmpty()) {
            return collect();
        }

        // Recommend PUBLISHED trips with those specialties, excluding already-signed-up trips
        $signedUpTripIds = TripSignup::where('user_id', $user->id)
            ->pluck('trip_id');

        return Trip::where('status', TripStatus::PUBLISHED->value)
            ->whereIn('specialty', $specialties)
            ->whereNotIn('id', $signedUpTripIds)
            ->orderByDesc('booking_count')
            ->limit($limit)
            ->get();
    }
}
