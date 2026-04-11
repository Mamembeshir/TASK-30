<?php

namespace App\Console\Commands;

use App\Services\SettlementService;
use Illuminate\Console\Command;

class CloseDailySettlement extends Command
{
    protected $signature   = 'medvoyage:close-settlement {date? : The date to settle (Y-m-d, defaults to today)}';
    protected $description = 'Close the daily payment settlement and reconcile';

    public function handle(SettlementService $service): int
    {
        // Default to the facility's local calendar date — NOT the server's
        // APP_TIMEZONE (UTC). The scheduler fires this command at 23:59
        // facility time; if we resolved `now()->toDateString()` in UTC,
        // America/New_York 23:59 = 04:59 UTC *the next day*, so the command
        // would close tomorrow's empty settlement instead of today's
        // just-closed business day. The explicit `date` argument still wins
        // when an operator is manually settling a specific historical date.
        $facilityTz = config('app.facility_timezone', config('app.timezone', 'UTC'));
        $date = $this->argument('date') ?? now($facilityTz)->toDateString();

        $this->info("Closing settlement for {$date} ({$facilityTz})...");

        try {
            $settlement = $service->closeDailySettlement($date, 'settlement.close.' . $date);
            $this->info("Status: {$settlement->status->value}");
            $this->info("Payments: " . formatCurrency($settlement->total_payments_cents));
            $this->info("Refunds:  " . formatCurrency($settlement->total_refunds_cents));
            $this->info("Net:      " . formatCurrency($settlement->net_amount_cents));
            $this->info("Variance: " . formatCurrency($settlement->variance_cents));

            if ($settlement->hasVariance()) {
                $this->warn("⚠  Variance detected — exceptions created.");
            } else {
                $this->info("✓  Reconciled.");
            }
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
            return 1;
        }

        return 0;
    }
}
