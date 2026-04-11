<?php

use App\Enums\ExceptionStatus;
use App\Enums\PaymentStatus;
use App\Enums\SettlementStatus;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\Settlement;
use App\Models\SettlementException;
use App\Models\Trip;
use App\Models\TripSignup;
use App\Models\User;
use App\Services\SettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

// Build a CONFIRMED trip signup paying a trip priced at $priceCents, linked to
// a CONFIRMED Payment of $paidCents. Used to drive the obligation-side and
// cash-side of reconciliation in lockstep (set price == paid for zero variance,
// or diverge them to engineer a controlled variance).
function wireObligation(int $priceCents, int $paidCents): Payment
{
    $trip    = Trip::factory()->create(['price_cents' => $priceCents]);
    $payment = Payment::factory()->confirmed()->create([
        'amount_cents' => $paidCents,
        'confirmed_at' => now(),
    ]);
    TripSignup::factory()->confirmed()->create([
        'trip_id'    => $trip->id,
        'payment_id' => $payment->id,
    ]);
    return $payment;
}

// ── FIN-06 / FIN-07: Close settlement ─────────────────────────────────────────

it('creates a RECONCILED settlement when variance is zero', function () {
    $date = now()->toDateString();
    // No payments, no expected → net = 0, expected = 0, variance = 0 → RECONCILED

    $settlement = app(SettlementService::class)->closeDailySettlement($date, (string) Str::uuid());

    expect($settlement->status)->toBe(SettlementStatus::RECONCILED)
        ->and($settlement->total_payments_cents)->toBe(0)
        ->and($settlement->variance_cents)->toBe(0);
});

it('sets total_payments_cents correctly from confirmed payments', function () {
    $date = now()->toDateString();

    // Wire three coherent obligations: each is a $10 trip paid with a $10
    // confirmed payment. The obligation-side expected total (3000) lines up
    // with the cash-side net (3000), so variance is 0 → RECONCILED — and
    // total_payments_cents reflects the sum of the linked payments.
    wireObligation(priceCents: 1000, paidCents: 1000);
    wireObligation(priceCents: 1000, paidCents: 1000);
    wireObligation(priceCents: 1000, paidCents: 1000);

    $settlement = app(SettlementService::class)->closeDailySettlement($date, (string) Str::uuid());

    expect($settlement->total_payments_cents)->toBe(3000)
        ->and($settlement->expected_amount_cents)->toBe(3000)
        ->and($settlement->variance_cents)->toBe(0)
        ->and($settlement->status)->toBe(SettlementStatus::RECONCILED);
});

it('creates an EXCEPTION settlement when variance exceeds 1 cent', function () {
    $date = now()->toDateString();

    // Engineer a controlled variance: two $100 trips, but each linked
    // payment only collected $50. Obligation-side expected = 20000, cash
    // side net = 10000, variance = -10000 → EXCEPTION with an OPEN record.
    wireObligation(priceCents: 10000, paidCents: 5000);
    wireObligation(priceCents: 10000, paidCents: 5000);

    $settlement = app(SettlementService::class)->closeDailySettlement($date, (string) Str::uuid());

    expect($settlement->status)->toBe(SettlementStatus::EXCEPTION);
    expect($settlement->expected_amount_cents)->toBe(20000);
    expect($settlement->net_amount_cents)->toBe(10000);
    expect($settlement->exceptions()->count())->toBe(1);
    expect($settlement->exceptions()->first()->status)->toBe(ExceptionStatus::OPEN);
});

it('links confirmed payments to settlement', function () {
    $date = now()->toDateString();
    $payments = Payment::factory()->confirmed()->count(2)->create(['confirmed_at' => now()]);

    $settlement = app(SettlementService::class)->closeDailySettlement($date, (string) Str::uuid());

    foreach ($payments as $payment) {
        expect($payment->fresh()->settlement_id)->toBe($settlement->id);
    }
});

it('throws when settlement is already RECONCILED', function () {
    $settlement = Settlement::factory()->reconciled()->forToday()->create();

    expect(fn () => app(SettlementService::class)->closeDailySettlement(now()->toDateString(), (string) Str::uuid()))
        ->toThrow(RuntimeException::class, 'reconciled');
});

it('generates a statement CSV file', function () {
    $date = now()->toDateString();
    Payment::factory()->confirmed()->create(['amount_cents' => 2500, 'confirmed_at' => now()]);

    $settlement = app(SettlementService::class)->closeDailySettlement($date, (string) Str::uuid());

    expect($settlement->statement_file_path)->not->toBeNull();
    expect(Storage::disk('local')->exists($settlement->statement_file_path))->toBeTrue();
});

it('closeDailySettlement is idempotent on the same key', function () {
    $date = now()->toDateString();
    $key  = (string) Str::uuid();
    $svc  = app(SettlementService::class);

    $first  = $svc->closeDailySettlement($date, $key);
    $second = $svc->closeDailySettlement($date, $key);

    expect($first->id)->toBe($second->id)
        ->and($second->status)->toBe(SettlementStatus::RECONCILED);
});

// ── FIN-08: Resolve exception ──────────────────────────────────────────────────

it('resolves an OPEN exception', function () {
    $user      = User::factory()->create();
    $exception = SettlementException::factory()->create(['status' => ExceptionStatus::OPEN->value]);

    $resolved = app(SettlementService::class)->resolveException(
        $exception, ExceptionStatus::RESOLVED, 'Confirmed by bank.', $user, (string) Str::uuid()
    );

    expect($resolved->status)->toBe(ExceptionStatus::RESOLVED)
        ->and($resolved->resolution_note)->toBe('Confirmed by bank.')
        ->and($resolved->resolved_by)->toBe($user->id);
});

it('writes off an OPEN exception', function () {
    $user      = User::factory()->create();
    $exception = SettlementException::factory()->create(['status' => ExceptionStatus::OPEN->value]);

    $resolved = app(SettlementService::class)->resolveException(
        $exception, ExceptionStatus::WRITTEN_OFF, 'Approved by manager.', $user, (string) Str::uuid()
    );

    expect($resolved->status)->toBe(ExceptionStatus::WRITTEN_OFF);
});

it('rejects resolving an already-resolved exception', function () {
    $user      = User::factory()->create();
    $exception = SettlementException::factory()->create(['status' => ExceptionStatus::RESOLVED->value]);

    expect(fn () => app(SettlementService::class)->resolveException(
        $exception, ExceptionStatus::RESOLVED, 'Again.', $user, (string) Str::uuid()
    ))->toThrow(RuntimeException::class, 'already resolved');
});

it('resolveException is idempotent on the same key', function () {
    $user      = User::factory()->create();
    $exception = SettlementException::factory()->create(['status' => ExceptionStatus::OPEN->value]);
    $key       = (string) Str::uuid();
    $svc       = app(SettlementService::class);

    $first  = $svc->resolveException($exception, ExceptionStatus::RESOLVED, 'Bank confirmed.', $user, $key);
    $second = $svc->resolveException($exception->fresh(), ExceptionStatus::RESOLVED, 'Bank confirmed.', $user, $key);

    expect($first->id)->toBe($second->id)
        ->and($second->status)->toBe(ExceptionStatus::RESOLVED);
});

// ── FIN-09: Re-reconcile ───────────────────────────────────────────────────────

it('re-reconciles a settlement with no open exceptions', function () {
    $settlement = Settlement::factory()->withException()->forToday()->create();
    $exception  = SettlementException::factory()->create([
        'settlement_id' => $settlement->id,
        'status'        => ExceptionStatus::RESOLVED->value,
    ]);

    $reconciled = app(SettlementService::class)->reReconcile($settlement, (string) Str::uuid());

    expect($reconciled->status)->toBe(SettlementStatus::RECONCILED);
});

it('blocks re-reconcile when open exceptions remain', function () {
    $settlement = Settlement::factory()->withException()->forToday()->create();
    SettlementException::factory()->create([
        'settlement_id' => $settlement->id,
        'status'        => ExceptionStatus::OPEN->value,
    ]);

    expect(fn () => app(SettlementService::class)->reReconcile($settlement, (string) Str::uuid()))
        ->toThrow(RuntimeException::class, 'open exception');
});

it('blocks re-reconcile on a non-EXCEPTION settlement', function () {
    $settlement = Settlement::factory()->reconciled()->forToday()->create();

    expect(fn () => app(SettlementService::class)->reReconcile($settlement, (string) Str::uuid()))
        ->toThrow(RuntimeException::class);
});

it('reReconcile is idempotent on the same key', function () {
    $settlement = Settlement::factory()->withException()->forToday()->create();
    SettlementException::factory()->create([
        'settlement_id' => $settlement->id,
        'status'        => ExceptionStatus::RESOLVED->value,
    ]);
    $key = (string) Str::uuid();
    $svc = app(SettlementService::class);

    $first  = $svc->reReconcile($settlement, $key);
    $second = $svc->reReconcile($settlement->fresh(), $key);

    expect($first->id)->toBe($second->id)
        ->and($second->status)->toBe(SettlementStatus::RECONCILED);
});

// ── Audit Issue 5: statement export traceability ─────────────────────────────
//
// exportStatement() must emit a `settlement.statement_exported` audit row on
// every invocation — both when it has to generate the file fresh and when it
// returns a cached path. Before the fix the method had no audit call at all.

it('records an audit entry on first export (file generation)', function () {
    Storage::fake('local');

    $actor      = User::factory()->create();
    $this->actingAs($actor);

    $settlement = Settlement::factory()->reconciled()->forToday()->create();

    app(SettlementService::class)->exportStatement($settlement);

    $audit = AuditLog::where('action', 'settlement.statement_exported')
        ->where('entity_id', $settlement->id)
        ->latest('created_at')
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->actor_id)->toBe($actor->id)
        ->and($audit->after_data['regenerated'])->toBeTrue()
        ->and($audit->after_data['file_path'])->toContain('statements/');
});

it('records an audit entry for the cached-file branch', function () {
    Storage::fake('local');

    $actor = User::factory()->create();
    $this->actingAs($actor);

    // Pre-stage a settlement whose statement file already exists on disk, so
    // exportStatement() returns the cached path instead of regenerating.
    $settlement = Settlement::factory()->reconciled()->forToday()->create();
    $cachedPath = 'statements/preexisting.csv';
    Storage::disk('local')->put($cachedPath, "pre-generated\n");
    $settlement->update(['statement_file_path' => $cachedPath]);

    app(SettlementService::class)->exportStatement($settlement->fresh());

    $audit = AuditLog::where('action', 'settlement.statement_exported')
        ->where('entity_id', $settlement->id)
        ->latest('created_at')
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->after_data['regenerated'])->toBeFalse()
        ->and($audit->after_data['file_path'])->toBe($cachedPath);
});
