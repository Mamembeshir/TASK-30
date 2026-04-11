<?php

namespace App\Strategies;

use App\Models\User;
use Illuminate\Support\Collection;

interface RecommendationStrategy
{
    /**
     * A unique machine-readable key for this strategy.
     */
    public function key(): string;

    /**
     * A human-readable label shown in the UI.
     */
    public function label(): string;

    /**
     * Return a ranked list of Trip models for the given user.
     *
     * @return Collection<int, \App\Models\Trip>
     */
    public function recommend(User $user, int $limit = 5): Collection;
}
