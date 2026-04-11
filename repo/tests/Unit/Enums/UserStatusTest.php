<?php

use App\Enums\UserStatus;

// ── allowedTransitions ────────────────────────────────────────────────────────

it('PENDING can only transition to ACTIVE', function () {
    expect(UserStatus::PENDING->allowedTransitions())->toBe([UserStatus::ACTIVE]);
});

it('ACTIVE can transition to SUSPENDED and DEACTIVATED', function () {
    $transitions = UserStatus::ACTIVE->allowedTransitions();
    expect($transitions)->toContain(UserStatus::SUSPENDED)
                        ->and($transitions)->toContain(UserStatus::DEACTIVATED);
});

it('SUSPENDED can transition to ACTIVE and DEACTIVATED', function () {
    $transitions = UserStatus::SUSPENDED->allowedTransitions();
    expect($transitions)->toContain(UserStatus::ACTIVE)
                        ->and($transitions)->toContain(UserStatus::DEACTIVATED);
});

it('DEACTIVATED has no allowed transitions — terminal state', function () {
    expect(UserStatus::DEACTIVATED->allowedTransitions())->toBe([]);
});

// ── canTransitionTo ───────────────────────────────────────────────────────────

it('returns true for valid transitions', function (UserStatus $from, UserStatus $to) {
    expect($from->canTransitionTo($to))->toBeTrue();
})->with([
    [UserStatus::PENDING,   UserStatus::ACTIVE],
    [UserStatus::ACTIVE,    UserStatus::SUSPENDED],
    [UserStatus::ACTIVE,    UserStatus::DEACTIVATED],
    [UserStatus::SUSPENDED, UserStatus::ACTIVE],
    [UserStatus::SUSPENDED, UserStatus::DEACTIVATED],
]);

it('returns false for invalid transitions', function (UserStatus $from, UserStatus $to) {
    expect($from->canTransitionTo($to))->toBeFalse();
})->with([
    [UserStatus::DEACTIVATED, UserStatus::ACTIVE],
    [UserStatus::DEACTIVATED, UserStatus::SUSPENDED],
    [UserStatus::DEACTIVATED, UserStatus::PENDING],
    [UserStatus::ACTIVE,      UserStatus::PENDING],
    [UserStatus::ACTIVE,      UserStatus::ACTIVE],
]);

// ── canLogin ──────────────────────────────────────────────────────────────────

it('only ACTIVE status can login', function () {
    expect(UserStatus::ACTIVE->canLogin())->toBeTrue();
    expect(UserStatus::PENDING->canLogin())->toBeFalse();
    expect(UserStatus::SUSPENDED->canLogin())->toBeFalse();
    expect(UserStatus::DEACTIVATED->canLogin())->toBeFalse();
});

// ── badgeVariant ──────────────────────────────────────────────────────────────

it('returns correct badge variants', function () {
    expect(UserStatus::ACTIVE->badgeVariant())->toBe('success');
    expect(UserStatus::PENDING->badgeVariant())->toBe('warning');
    expect(UserStatus::SUSPENDED->badgeVariant())->toBe('danger');
    expect(UserStatus::DEACTIVATED->badgeVariant())->toBe('neutral');
});
