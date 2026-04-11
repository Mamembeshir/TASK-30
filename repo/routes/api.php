<?php

/**
 * REST API routes.
 *
 * These routes satisfy the prompt's "REST-style endpoints consumed by Livewire
 * components" requirement by exposing the same service layer that Livewire
 * components use.  Authentication uses the web (session) guard — the same
 * session that Livewire components run under — so no separate token setup is
 * required for in-browser consumers.
 *
 * All state-mutation endpoints accept an optional `Idempotency-Key` request
 * header (or `idempotency_key` body field) and propagate it to the service
 * layer, providing the same retry-safe guarantees as Livewire actions.
 *
 * Full reference: docs/api-spec.md §"REST API (/api/*)"
 */

use App\Http\Controllers\Api\CredentialingApiController;
use App\Http\Controllers\Api\PaymentApiController;
use App\Http\Controllers\Api\TripApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public read endpoints (authenticated, any role)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:web', 'account.status'])->group(function () {

    // ── Trips ─────────────────────────────────────────────────────────────────

    Route::get('/trips',             [TripApiController::class, 'index']);
    Route::get('/trips/{trip}',      [TripApiController::class, 'show']);

    // Seat hold and waitlist — member-facing mutations
    Route::post('/trips/{trip}/hold',      [TripApiController::class, 'hold']);
    Route::post('/trips/{trip}/waitlist',  [TripApiController::class, 'joinWaitlist']);

    // ── Finance (FINANCE_SPECIALIST or ADMIN) ────────────────────────────────

    Route::middleware('finance')->group(function () {
        Route::post('/payments',                    [PaymentApiController::class, 'record']);
        Route::post('/payments/{payment}/void',     [PaymentApiController::class, 'void']);
        Route::post('/payments/{payment}/confirm',  [PaymentApiController::class, 'confirm']);
    });

    // ── Credentialing (CREDENTIALING_REVIEWER or ADMIN) ─────────────────────

    Route::middleware('credentialing')->group(function () {
        Route::post('/credentialing/cases/{case}/assign',   [CredentialingApiController::class, 'assignReviewer']);
        Route::post('/credentialing/cases/{case}/approve',  [CredentialingApiController::class, 'approve']);
        Route::post('/credentialing/cases/{case}/reject',   [CredentialingApiController::class, 'reject']);
    });
});
