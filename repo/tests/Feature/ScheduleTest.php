<?php

use Illuminate\Console\Scheduling\Schedule;

/**
 * Pinned schedule expectations — keeps the cron cadence and the facility-local
 * timezone for daily jobs in lock-step with docs/design.md "Scheduled Commands".
 */

function scheduledEvents(): array
{
    /** @var Schedule $schedule */
    $schedule = app(Schedule::class);
    $events   = [];

    foreach ($schedule->events() as $event) {
        $events[$event->command ?? $event->description ?? ''] = $event;
    }

    return $events;
}

function findEvent(string $needle)
{
    foreach (scheduledEvents() as $key => $event) {
        if (str_contains((string) $key, $needle)) {
            return $event;
        }
    }
    return null;
}

it('runs hold/offer expiry sweeps every 10 minutes as a safety net', function () {
    // Primary expiry path is the real-time delayed queue jobs dispatched from
    // SeatService/WaitlistService (see App\Jobs\*). These scheduled commands
    // are demoted to a safety-net sweep, so the cadence is */10, not */1.
    $hold  = findEvent('expire-seat-holds');
    $offer = findEvent('expire-waitlist-offers');

    expect($hold)->not->toBeNull()
        ->and($hold->expression)->toBe('*/10 * * * *');

    expect($offer)->not->toBeNull()
        ->and($offer->expression)->toBe('*/10 * * * *');
});

it('runs seat reconciliation every five minutes', function () {
    $event = findEvent('reconcile-seats');
    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('*/5 * * * *');
});

it('pins daily settlement to 23:59 facility time', function () {
    $event = findEvent('close-settlement');

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('59 23 * * *')
        ->and($event->timezone)->toBe(config('app.facility_timezone'));
});

it('pins license expiry to 01:00 facility time', function () {
    $event = findEvent('check-license-expiry');

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 1 * * *')
        ->and($event->timezone)->toBe(config('app.facility_timezone'));
});
