<?php

namespace App\Observers;

use App\Models\Doctor;
use App\Models\SearchTerm;

/**
 * Populates SearchTerm table when a Doctor is created.
 * Per questions.md 5.1: doctor name (username) and specialty are indexed.
 */
class DoctorObserver
{
    public function created(Doctor $doctor): void
    {
        $this->indexDoctor($doctor);
    }

    public function updated(Doctor $doctor): void
    {
        // Re-index on specialty change
        if ($doctor->isDirty('specialty')) {
            $this->indexDoctor($doctor);
        }
    }

    private function indexDoctor(Doctor $doctor): void
    {
        // Doctor's specialty
        if ($doctor->specialty) {
            SearchTerm::upsertTerm($doctor->specialty, 'specialty');
        }

        // Doctor's username (loaded via user relationship)
        $doctor->loadMissing('user');
        if ($doctor->user?->username) {
            SearchTerm::upsertTerm($doctor->user->username, 'doctor');
        }
    }
}
