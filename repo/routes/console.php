<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule seat hold expiry check every 2 minutes
Schedule::command('medvoyage:expire-seat-holds')->everyTwoMinutes();

// Schedule waitlist offer expiry check every 2 minutes
Schedule::command('medvoyage:expire-waitlist-offers')->everyTwoMinutes();

// Daily settlement close at 23:59 facility time
Schedule::command('medvoyage:close-settlement')->dailyAt('23:59');

// Seat consistency reconciliation every 10 minutes
Schedule::command('medvoyage:reconcile-seats')->everyTenMinutes();

// CRED-08: Daily license expiry check
Schedule::command('medvoyage:check-license-expiry')->dailyAt('01:00');
