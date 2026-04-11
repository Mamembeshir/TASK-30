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
        $date = $this->argument('date') ?? now()->toDateString();

        $this->info("Closing settlement for {$date}...");

        try {
            $settlement = $service->closeDailySettlement($date);
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
