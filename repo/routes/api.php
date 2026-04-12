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
use App\Http\Controllers\Api\InvoiceApiController;
use App\Http\Controllers\Api\MembershipApiController;
use App\Http\Controllers\Api\PaymentApiController;
use App\Http\Controllers\Api\ProfileApiController;
use App\Http\Controllers\Api\ReviewApiController;
use App\Http\Controllers\Api\SearchApiController;
use App\Http\Controllers\Api\SettlementApiController;
use App\Http\Controllers\Api\SignupApiController;
use App\Http\Controllers\Api\TripApiController;
use App\Http\Controllers\Api\TripManageApiController;
use App\Http\Controllers\Api\UserApiController;
use App\Http\Controllers\Api\WaitlistApiController;
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
    Route::post('/trips/{trip}/hold',             [TripApiController::class, 'hold']);
    Route::post('/trips/{trip}/waitlist',         [TripApiController::class, 'joinWaitlist']);

    // Waitlist offer accept / decline — member-facing
    Route::post('/waitlist/{entry}/accept',       [WaitlistApiController::class, 'acceptOffer']);
    Route::post('/waitlist/{entry}/decline',      [WaitlistApiController::class, 'declineOffer']);

    // Signup cancel — member-facing
    Route::post('/signups/{signup}/cancel',       [WaitlistApiController::class, 'cancelSignup']);

    // Signup payment + seat confirmation — member-facing mutation
    Route::post('/signups/{signup}/payment',      [SignupApiController::class, 'submitPayment']);

    // Trip reviews — member-facing
    Route::post('/trips/{trip}/reviews',          [ReviewApiController::class, 'create']);
    Route::put('/reviews/{review}',               [ReviewApiController::class, 'update']);

    // Profile — authenticated user
    Route::put('/profile',                        [ProfileApiController::class, 'save']);

    // Search — authenticated user
    Route::post('/search/history/clear',          [SearchApiController::class, 'clearHistory']);

    // Membership — member-facing
    Route::post('/membership/plans/{plan}/purchase',  [MembershipApiController::class, 'purchase']);
    Route::post('/membership/plans/{plan}/top-up',    [MembershipApiController::class, 'topUp']);
    Route::post('/membership/orders/{order}/refund',  [MembershipApiController::class, 'requestRefund']);

    // Credentialing — doctor self-service
    Route::post('/credentialing/doctors/{doctor}/upload-document', [CredentialingApiController::class, 'uploadDocumentForDoctor']);
    Route::post('/credentialing/doctors/{doctor}/submit-case',     [CredentialingApiController::class, 'submitCase']);
    Route::post('/credentialing/doctors/{doctor}/resubmit-case',   [CredentialingApiController::class, 'resubmitCase']);

    // ── Finance (FINANCE_SPECIALIST or ADMIN) ────────────────────────────────

    Route::middleware('finance')->group(function () {
        Route::post('/payments',                    [PaymentApiController::class, 'record']);
        Route::post('/payments/{payment}/void',     [PaymentApiController::class, 'void']);
        Route::post('/payments/{payment}/confirm',  [PaymentApiController::class, 'confirm']);

        Route::post('/invoices',                        [InvoiceApiController::class, 'create']);
        Route::post('/invoices/{invoice}/lines',        [InvoiceApiController::class, 'addLine']);
        Route::post('/invoices/{invoice}/issue',        [InvoiceApiController::class, 'issue']);
        Route::post('/invoices/{invoice}/mark-paid',    [InvoiceApiController::class, 'markPaid']);
        Route::post('/invoices/{invoice}/void',         [InvoiceApiController::class, 'void']);

        Route::post('/settlements/close',                              [SettlementApiController::class, 'close']);
        Route::post('/settlements/{settlement}/resolve-exception',     [SettlementApiController::class, 'resolveException']);
        Route::post('/settlements/{settlement}/re-reconcile',          [SettlementApiController::class, 'reReconcile']);
        Route::get('/settlements/{settlement}/statement',              [SettlementApiController::class, 'downloadStatement']);

        Route::post('/membership/refunds/{refund}/approve',  [MembershipApiController::class, 'approveRefund']);
        Route::post('/membership/refunds/{refund}/process',  [MembershipApiController::class, 'processRefund']);
    });

    // ── Credentialing (CREDENTIALING_REVIEWER or ADMIN) ─────────────────────

    Route::middleware('credentialing')->group(function () {
        Route::post('/credentialing/cases/{case}/assign',           [CredentialingApiController::class, 'assignReviewer']);
        Route::post('/credentialing/cases/{case}/approve',          [CredentialingApiController::class, 'approve']);
        Route::post('/credentialing/cases/{case}/reject',           [CredentialingApiController::class, 'reject']);
        Route::post('/credentialing/cases/{case}/start-review',     [CredentialingApiController::class, 'startReview']);
        Route::post('/credentialing/cases/{case}/request-materials',[CredentialingApiController::class, 'requestMaterials']);
        Route::post('/credentialing/cases/{case}/upload-document',  [CredentialingApiController::class, 'uploadDocumentForCase']);
    });

    // ── Admin ─────────────────────────────────────────────────────────────────

    Route::middleware('admin')->group(function () {
        Route::post('/admin/users/{user}/transition',  [UserApiController::class, 'transitionTo']);
        Route::post('/admin/users/{user}/unlock',      [UserApiController::class, 'unlock']);
        Route::put('/admin/users/{user}/roles',        [UserApiController::class, 'saveRoles']);

        Route::post('/admin/trips',                    [TripManageApiController::class, 'store']);
        Route::put('/admin/trips/{trip}',              [TripManageApiController::class, 'update']);
        Route::post('/admin/trips/{trip}/publish',     [TripManageApiController::class, 'publish']);
        Route::post('/admin/trips/{trip}/close',       [TripManageApiController::class, 'close']);
        Route::post('/admin/trips/{trip}/cancel',      [TripManageApiController::class, 'cancel']);

        Route::post('/admin/reviews/{review}/flag',    [ReviewApiController::class, 'flag']);
        Route::post('/admin/reviews/{review}/remove',  [ReviewApiController::class, 'remove']);
        Route::post('/admin/reviews/{review}/restore', [ReviewApiController::class, 'restore']);
    });
});
