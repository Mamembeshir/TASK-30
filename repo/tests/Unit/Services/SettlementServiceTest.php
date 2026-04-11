<?php

use App\Enums\ExceptionStatus;
use App\Enums\PaymentStatus;
use App\Enums\SettlementStatus;
use App\Models\Payment;
use App\Models\Settlement;
use App\Models\SettlementException;
use App\Models\User;
use App\Services\SettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── FIN-06 / FIN-07: Close settlement ─────────────────────────────────────────

it('creates a RECONCILED settlement when variance is zero', function () {
    $date = now()->toDateString();
    // No payments, no expected → net = 0, expected = 0, variance = 0 → RECONCILED

    $settlement = app(SettlementService::class)->closeDailySettlement($date);

    expect($settlement->status)->toBe(SettlementStatus::RECONCILED)
        ->and($settlement->total_payments_cents)->toBe(0)
        ->and($settlement->variance_cents)->toBe(0);
});

it('sets total_payments_cents correctly from confirmed payments', function () {
    $date = now()->toDateString();
    Payment::factory()->confirmed()->count(3)->create([
        'amount_cents' => 1000,
        'confirmed_at' => now(),
    ]);
    // Pre-set expected to match actual to get RECONCILED
    Settlement::firstOrCreate(
        ['settlement_date' => $date],
        ['status' => SettlementStatus::OPEN->value, 'total_payments_cents' => 0, 'total_refunds_cents' => 0,
         'net_amount_cents' => 0, 'expected_amount_cents' => 3000, 'variance_cents' => 0, 'version' => 1]
    );

    $settlement = app(SettlementService::class)->closeDailySettlement($date);

    expect($settlement->total_payments_cents)->toBe(3000)
        ->and($settlement->status)->toBe(SettlementStatus::RECONCILED);
});

it('creates an EXCEPTION settlement when variance exceeds 1 cent', function () {
    $date = now()->toDateString();

    Payment::factory()->confirmed()->count(2)->create([
        'amount_cents' => 5000,
        'confirmed_at' => now(),
    ]);

    // Pre-set expected higher than actual to create variance
    Settlement::firstOrCreate(
        ['settlement_date' => $date],
        ['status' => SettlementStatus::OPEN->value, 'total_payments_cents' => 0, 'total_refunds_cents' => 0,
         'net_amount_cents' => 0, 'expected_amount_cents' => 20000, 'variance_cents' => 0, 'version' => 1]
    );

    $settlement = app(SettlementService::class)->closeDailySettlement($date);

    expect($settlement->status)->toBe(SettlementStatus::EXCEPTION);
    expect($settlement->exceptions()->count())->toBe(1);
    expect($settlement->exceptions()->first()->status)->toBe(ExceptionStatus::OPEN);
});

it('links confirmed payments to settlement', function () {
    $date = now()->toDateString();
    $payments = Payment::factory()->confirmed()->count(2)->create(['confirmed_at' => now()]);

    $settlement = app(SettlementService::class)->closeDailySettlement($date);

    foreach ($payments as $payment) {
        expect($payment->fresh()->settlement_id)->toBe($settlement->id);
    }
});

it('throws when settlement is already RECONCILED', function () {
    $settlement = Settlement::factory()->reconciled()->forToday()->create();

    expect(fn () => app(SettlementService::class)->closeDailySettlement(now()->toDateString()))
        ->toThrow(RuntimeException::class, 'reconciled');
});

it('generates a statement CSV file', function () {
    $date = now()->toDateString();
    Payment::factory()->confirmed()->create(['amount_cents' => 2500, 'confirmed_at' => now()]);

    $settlement = app(SettlementService::class)->closeDailySettlement($date);

    expect($settlement->statement_file_path)->not->toBeNull();
    expect(Storage::disk('local')->exists($settlement->statement_file_path))->toBeTrue();
});

// ── FIN-08: Resolve exception ──────────────────────────────────────────────────

it('resolves an OPEN exception', function () {
    $user      = User::factory()->create();
    $exception = SettlementException::factory()->create(['status' => ExceptionStatus::OPEN->value]);

    $resolved = app(SettlementService::class)->resolveException(
        $exception, ExceptionStatus::RESOLVED, 'Confirmed by bank.', $user
    );

    expect($resolved->status)->toBe(ExceptionStatus::RESOLVED)
        ->and($resolved->resolution_note)->toBe('Confirmed by bank.')
        ->and($resolved->resolved_by)->toBe($user->id);
});

it('writes off an OPEN exception', function () {
    $user      = User::factory()->create();
    $exception = SettlementException::factory()->create(['status' => ExceptionStatus::OPEN->value]);

    $resolved = app(SettlementService::class)->resolveException(
        $exception, ExceptionStatus::WRITTEN_OFF, 'Approved by manager.', $user
    );

    expect($resolved->status)->toBe(ExceptionStatus::WRITTEN_OFF);
});

it('rejects resolving an already-resolved exception', function () {
    $user      = User::factory()->create();
    $exception = SettlementException::factory()->create(['status' => ExceptionStatus::RESOLVED->value]);

    expect(fn () => app(SettlementService::class)->resolveException(
        $exception, ExceptionStatus::RESOLVED, 'Again.', $user
    ))->toThrow(RuntimeException::class, 'already resolved');
});

// ── FIN-09: Re-reconcile ───────────────────────────────────────────────────────

it('re-reconciles a settlement with no open exceptions', function () {
    $settlement = Settlement::factory()->withException()->forToday()->create();
    $exception  = SettlementException::factory()->create([
        'settlement_id' => $settlement->id,
        'status'        => ExceptionStatus::RESOLVED->value,
    ]);

    $reconciled = app(SettlementService::class)->reReconcile($settlement);

    expect($reconciled->status)->toBe(SettlementStatus::RECONCILED);
});

it('blocks re-reconcile when open exceptions remain', function () {
    $settlement = Settlement::factory()->withException()->forToday()->create();
    SettlementException::factory()->create([
        'settlement_id' => $settlement->id,
        'status'        => ExceptionStatus::OPEN->value,
    ]);

    expect(fn () => app(SettlementService::class)->reReconcile($settlement))
        ->toThrow(RuntimeException::class, 'open exception');
});

it('blocks re-reconcile on a non-EXCEPTION settlement', function () {
    $settlement = Settlement::factory()->reconciled()->forToday()->create();

    expect(fn () => app(SettlementService::class)->reReconcile($settlement))
        ->toThrow(RuntimeException::class);
});
