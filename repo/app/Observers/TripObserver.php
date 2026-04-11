<?php

namespace App\Observers;

use App\Models\SearchTerm;
use App\Models\Trip;

/**
 * Populates SearchTerm table whenever a Trip is created or updated.
 * Per questions.md 5.1: title words, specialty, destination are indexed.
 */
class TripObserver
{
    public function created(Trip $trip): void
    {
        $this->indexTrip($trip);
    }

    public function updated(Trip $trip): void
    {
        $this->indexTrip($trip);
    }

    private function indexTrip(Trip $trip): void
    {
        // Destination as a phrase
        if ($trip->destination) {
            SearchTerm::upsertTerm($trip->destination, 'destination');
        }

        // Specialty as a phrase
        if ($trip->specialty) {
            SearchTerm::upsertTerm($trip->specialty, 'specialty');
        }

        // Individual title words (min 3 chars to avoid noise)
        if ($trip->title) {
            $words = array_filter(
                explode(' ', preg_replace('/[^a-zA-Z0-9 ]/', '', $trip->title)),
                fn ($w) => mb_strlen($w) >= 3
            );
            foreach (array_unique($words) as $word) {
                SearchTerm::upsertTerm($word, 'title');
            }
        }
    }
}
