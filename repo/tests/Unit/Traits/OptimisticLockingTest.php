<?php

use App\Exceptions\StaleRecordException;
use App\Models\User;
use App\Enums\UserStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('saves successfully when version matches', function () {
    $user = User::factory()->create(['version' => 1]);

    $user->username = 'updated_username';
    $result = $user->saveWithLock();

    expect($result)->toBeTrue();
    expect($user->fresh()->version)->toBe(2);
    expect($user->fresh()->username)->toBe('updated_username');
});

it('throws StaleRecordException when version is stale', function () {
    $user = User::factory()->create(['version' => 1]);

    // Simulate another process updating the record first
    User::where('id', $user->id)->update(['version' => 2]);

    $user->username = 'should_fail';

    expect(fn () => $user->saveWithLock())
        ->toThrow(StaleRecordException::class);
});

it('StaleRecordException has HTTP 409 code', function () {
    $e = new StaleRecordException('User');
    expect($e->getCode())->toBe(409);
});

it('does not persist changes when stale version is detected', function () {
    $user     = User::factory()->create(['username' => 'original', 'version' => 1]);
    $original = $user->username;

    User::where('id', $user->id)->update(['version' => 99]);

    $user->username = 'changed';

    try {
        $user->saveWithLock();
    } catch (StaleRecordException) {
        // expected
    }

    expect($user->fresh()->username)->toBe($original);
});

it('increments version on each successful saveWithLock', function () {
    $user = User::factory()->create(['version' => 1]);

    $user->username = 'v2';
    $user->saveWithLock();
    expect($user->fresh()->version)->toBe(2);

    $user->username = 'v3';
    $user->saveWithLock();
    expect($user->fresh()->version)->toBe(3);
});
