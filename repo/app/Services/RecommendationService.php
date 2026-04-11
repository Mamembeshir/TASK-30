<?php

namespace App\Services;

use App\Models\User;
use App\Strategies\RecommendationStrategy;
use Illuminate\Support\Collection;

class RecommendationService
{
    /**
     * Iterate config('recommendations.strategies'), instantiate each, call recommend().
     * Returns an array of sections, each with: key, label, trips (Collection<Trip>).
     *
     * New strategies are registered exclusively via config/recommendations.php —
     * no other code changes required (SRCH-08).
     *
     * @return array<int, array{key: string, label: string, trips: Collection}>
     */
    public function getRecommendations(User $user, int $limit = 5): array
    {
        $strategyClasses = config('recommendations.strategies', []);

        $sections = [];

        foreach ($strategyClasses as $class) {
            if (! class_exists($class)) {
                continue;
            }

            /** @var RecommendationStrategy $strategy */
            $strategy = app($class);

            $trips = $strategy->recommend($user, $limit);

            if ($trips->isEmpty()) {
                continue; // skip empty sections
            }

            $sections[] = [
                'key'   => $strategy->key(),
                'label' => $strategy->label(),
                'trips' => $trips,
            ];
        }

        return $sections;
    }
}
