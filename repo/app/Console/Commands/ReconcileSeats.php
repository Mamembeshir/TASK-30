<?php

namespace App\Console\Commands;

use App\Enums\SignupStatus;
use App\Enums\TripStatus;
use App\Models\Trip;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileSeats extends Command
{
    protected $signature   = 'medvoyage:reconcile-seats {--dry-run : Show discrepancies without fixing them}';
    protected $description = 'Reconcile available_seats by comparing against active signups. Fixes any drift.';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $trips  = Trip::whereIn('status', [
            TripStatus::PUBLISHED->value,
            TripStatus::FULL->value,
        ])->get();

        $fixed = 0;

        foreach ($trips as $trip) {
            $activeCount   = $trip->signups()
                ->whereIn('status', [SignupStatus::HOLD->value, SignupStatus::CONFIRMED->value])
                ->count();

            $expectedAvailable = max(0, $trip->total_seats - $activeCount);

            if ($trip->available_seats !== $expectedAvailable) {
                $this->warn(
                    "Trip {$trip->id} ({$trip->title}): available_seats={$trip->available_seats} "
                    . "but expected={$expectedAvailable} (total={$trip->total_seats}, active={$activeCount})"
                );

                if (! $dryRun) {
                    DB::transaction(function () use ($trip, $expectedAvailable) {
                        $trip->available_seats = $expectedAvailable;

                        // Correct the trip status based on the reconciled count
                        if ($expectedAvailable === 0 && $trip->status === TripStatus::PUBLISHED) {
                            $trip->status = TripStatus::FULL;
                        } elseif ($expectedAvailable > 0 && $trip->status === TripStatus::FULL) {
                            $trip->status = TripStatus::PUBLISHED;
                        }

                        $trip->saveQuietly();
                    });

                    $fixed++;
                }
            }
        }

        if ($dryRun) {
            $this->info("Dry-run complete. Checked {$trips->count()} trip(s).");
        } else {
            $this->info("Reconciliation complete. Fixed {$fixed} trip(s) out of {$trips->count()} checked.");
        }

        return self::SUCCESS;
    }
}
