<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled commands
|--------------------------------------------------------------------------
|
| Cadence is documented in docs/design.md §"Scheduled Commands". Daily jobs
| run in the facility's local timezone (config('app.facility_timezone'))
| so that the "23:59 facility time" settlement cutover is honored regardless
| of the server's APP_TIMEZONE (UTC in production).
|
*/

$facilityTz = config('app.facility_timezone', 'UTC');

// Hold and waitlist-offer expiry are driven in real time by delayed queue
// jobs (see App\Jobs\ReleaseExpiredHold, ExpireWaitlistOfferJob) dispatched
// by SeatService/WaitlistService at creation time — the user's 10-minute
// timer now fires precisely at the expiry instant over the WebSocket, not
// on a cron tick. These cron commands remain as a *safety net* only: they
// sweep up any records that would otherwise be stranded if the queue worker
// was down when the job was scheduled to run. Ten-minute cadence is enough
// for recovery while keeping polling load trivial.
Schedule::command('medvoyage:expire-seat-holds')->everyTenMinutes();
Schedule::command('medvoyage:expire-waitlist-offers')->everyTenMinutes();

// Seat consistency reconciliation — every 5 minutes per docs.
Schedule::command('medvoyage:reconcile-seats')->everyFiveMinutes();

// Daily settlement close at 23:59 *facility* time (not server UTC).
Schedule::command('medvoyage:close-settlement')
    ->dailyAt('23:59')
    ->timezone($facilityTz);

// CRED-08: Daily license expiry check at 01:00 facility time.
Schedule::command('medvoyage:check-license-expiry')
    ->dailyAt('01:00')
    ->timezone($facilityTz);
