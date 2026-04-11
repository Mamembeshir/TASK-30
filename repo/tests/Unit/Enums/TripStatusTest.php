<?php

use App\Enums\TripStatus;

it('allows valid status transitions', function (TripStatus $from, TripStatus $to) {
    expect($from->allowedTransitions())->toContain($to);
})->with([
    [TripStatus::DRAFT,     TripStatus::PUBLISHED],
    [TripStatus::DRAFT,     TripStatus::CANCELLED],
    [TripStatus::PUBLISHED, TripStatus::FULL],
    [TripStatus::PUBLISHED, TripStatus::CLOSED],
    [TripStatus::PUBLISHED, TripStatus::CANCELLED],
    [TripStatus::FULL,      TripStatus::PUBLISHED],
    [TripStatus::FULL,      TripStatus::CLOSED],
    [TripStatus::FULL,      TripStatus::CANCELLED],
    [TripStatus::CLOSED,    TripStatus::CANCELLED],
]);

it('rejects invalid status transitions', function (TripStatus $from, TripStatus $to) {
    expect($from->allowedTransitions())->not->toContain($to);
})->with([
    [TripStatus::CANCELLED, TripStatus::PUBLISHED],
    [TripStatus::CANCELLED, TripStatus::DRAFT],
    [TripStatus::DRAFT,     TripStatus::FULL],
    [TripStatus::CLOSED,    TripStatus::DRAFT],
]);

it('only accepts signups in PUBLISHED status', function () {
    expect(TripStatus::PUBLISHED->canAcceptSignups())->toBeTrue();
    expect(TripStatus::DRAFT->canAcceptSignups())->toBeFalse();
    expect(TripStatus::FULL->canAcceptSignups())->toBeFalse();
    expect(TripStatus::CANCELLED->canAcceptSignups())->toBeFalse();
});

it('returns correct badge variant for each status', function () {
    expect(TripStatus::PUBLISHED->badgeVariant())->toBe('success');
    expect(TripStatus::DRAFT->badgeVariant())->toBe('neutral');
    expect(TripStatus::CANCELLED->badgeVariant())->toBe('danger');
    expect(TripStatus::FULL->badgeVariant())->toBe('warning');
});
