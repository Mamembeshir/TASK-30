<?php

namespace App\Console\Commands;

use App\Enums\HoldReleaseReason;
use App\Enums\SignupStatus;
use App\Models\TripSignup;
use App\Services\SeatService;
use Illuminate\Console\Command;

class ExpireSeatHolds extends Command
{
    protected $signature   = 'medvoyage:expire-seat-holds';
    protected $description = 'Release seat holds that have passed their expiry time.';

    public function handle(SeatService $seatService): int
    {
        $expired = TripSignup::where('status', SignupStatus::HOLD->value)
            ->where('hold_expires_at', '<=', now())
            ->get();

        $count = 0;
        foreach ($expired as $signup) {
            try {
                $seatService->releaseSeat($signup, HoldReleaseReason::EXPIRED);
                $count++;
            } catch (\Throwable $e) {
                $this->error("Failed to expire signup {$signup->id}: {$e->getMessage()}");
            }
        }

        $this->info("Expired {$count} seat hold(s).");

        return self::SUCCESS;
    }
}
