<?php

/**
 * AuditService — idempotency key capture tests.
 *
 * AuditService::record() resolves the idempotency key from three sources in
 * priority order:
 *
 *   1. Idempotency-Key request header   (canonical)
 *   2. X-Idempotency-Key request header (legacy alias)
 *   3. idempotency_key request body field
 *
 * Source #3 is the path taken by in-process Livewire → controller calls,
 * which synthesise an Illuminate Request with the key in the body rather
 * than a header.
 */

use App\Models\User;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

// ── helpers ───────────────────────────────────────────────────────────────────

/**
 * Boot a minimal Request as the active request so AuditService::record() can
 * read headers / body via the request() helper, then restore the original
 * request afterwards.
 */
function withSyntheticRequest(Request $synthetic, Closure $callback): mixed
{
    $app     = app();
    $original = $app->make('request');

    $app->instance('request', $synthetic);
    $synthetic->setUserResolver(fn () => Auth::user());

    try {
        return $callback();
    } finally {
        $app->instance('request', $original);
    }
}

// ── tests ─────────────────────────────────────────────────────────────────────

it('captures idempotency key from Idempotency-Key header', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $request = Request::create('/test', 'POST');
    $request->headers->set('Idempotency-Key', 'header-canonical-key');

    $log = withSyntheticRequest($request, fn () =>
        AuditService::record('test.action', 'User', $user->id)
    );

    expect($log->idempotency_key)->toBe('header-canonical-key');
});

it('captures idempotency key from X-Idempotency-Key legacy header', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $request = Request::create('/test', 'POST');
    $request->headers->set('X-Idempotency-Key', 'legacy-header-key');

    $log = withSyntheticRequest($request, fn () =>
        AuditService::record('test.action', 'User', $user->id)
    );

    expect($log->idempotency_key)->toBe('legacy-header-key');
});

it('captures idempotency key from request body field (in-process Livewire path)', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    // Mirrors exactly how Livewire components build in-process requests:
    // the idempotency_key is passed in the POST body, not a header.
    $request = Request::create('/test', 'POST', [
        'idempotency_key' => 'body-field-key',
    ]);

    $log = withSyntheticRequest($request, fn () =>
        AuditService::record('test.action', 'User', $user->id)
    );

    expect($log->idempotency_key)->toBe('body-field-key');
});

it('prefers Idempotency-Key header over body field when both are present', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $request = Request::create('/test', 'POST', [
        'idempotency_key' => 'body-field-key',
    ]);
    $request->headers->set('Idempotency-Key', 'header-wins');

    $log = withSyntheticRequest($request, fn () =>
        AuditService::record('test.action', 'User', $user->id)
    );

    expect($log->idempotency_key)->toBe('header-wins');
});

it('stores null idempotency key when no source provides one', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $request = Request::create('/test', 'POST');

    $log = withSyntheticRequest($request, fn () =>
        AuditService::record('test.action', 'User', $user->id)
    );

    expect($log->idempotency_key)->toBeNull();
});
