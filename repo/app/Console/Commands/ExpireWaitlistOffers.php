<?php

namespace App\Console\Commands;

use App\Enums\WaitlistStatus;
use App\Models\TripWaitlistEntry;
use App\Services\WaitlistService;
use Illuminate\Console\Command;

class ExpireWaitlistOffers extends Command
{
    protected $signature   = 'medvoyage:expire-waitlist-offers';
    protected $description = 'Expire waitlist offers that have not been accepted in time and offer to the next person.';

    public function handle(WaitlistService $waitlistService): int
    {
        $expired = TripWaitlistEntry::where('status', WaitlistStatus::OFFERED->value)
            ->where('offer_expires_at', '<=', now())
            ->get();

        $count = 0;
        foreach ($expired as $entry) {
            try {
                $waitlistService->expireOffer($entry);
                $count++;
            } catch (\Throwable $e) {
                $this->error("Failed to expire waitlist entry {$entry->id}: {$e->getMessage()}");
            }
        }

        $this->info("Expired {$count} waitlist offer(s).");

        return self::SUCCESS;
    }
}
