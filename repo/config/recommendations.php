<?php

/**
 * Recommendation strategy registry.
 *
 * Add new strategies here — each must implement App\Strategies\RecommendationStrategy.
 * Strategies are presented in display order.
 * New strategies can be registered here without any other code changes (SRCH-08).
 */
return [
    'strategies' => [
        \App\Strategies\MostBookedLast90Days::class,
        \App\Strategies\SimilarSpecialty::class,
        \App\Strategies\UpcomingSoonest::class,
    ],
];
