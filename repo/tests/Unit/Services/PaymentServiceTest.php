<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\SignupStatus;
use App\Enums\TenderType;
use App\Models\MembershipOrder;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\TripSignup;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

// ── FIN-01: Record ─────────────────────────────────────────────────────────────

it('records a payment in RECORDED status', function () {
    $user = User::factory()->create();
    $key  = (string) Str::uuid();

    $payment = app(PaymentService::class)->recordPayment(
        $user, TenderType::CASH, 5000, null, $key
    );

    expect($payment->status)->toBe(PaymentStatus::RECORDED)
        ->and($payment->amount_cents)->toBe(5000)
        ->and($payment->user_id)->toBe($user->id);
});

it('is idempotent on idempotency_key', function () {
    $user = User::factory()->create();
    $key  = (string) Str::uuid();

    $a = app(PaymentService::class)->recordPayment($user, TenderType::CASH, 5000, null, $key);
    $b = app(PaymentService::class)->recordPayment($user, TenderType::CASH, 9999, null, $key);

    expect($a->id)->toBe($b->id)
        ->and($b->amount_cents)->toBe(5000); // original amount preserved
});

it('rejects zero or negative amount', function () {
    $user = User::factory()->create();

    expect(fn () => app(PaymentService::class)->recordPayment($user, TenderType::CASH, 0, null, (string) Str::uuid()))
        ->toThrow(RuntimeException::class, 'positive');
});

// ── FIN-02: Confirm ────────────────────────────────────────────────────────────

it('confirms a RECORDED payment', function () {
    $payment = Payment::factory()->recorded()->create();

    $confirmed = app(PaymentService::class)->confirmPayment($payment, 'evt-abc-123');

    expect($confirmed->status)->toBe(PaymentStatus::CONFIRMED)
        ->and($confirmed->confirmation_event_id)->toBe('evt-abc-123')
        ->and($confirmed->confirmed_at)->not->toBeNull();
});

it('confirm is idempotent on same event id', function () {
    $payment = Payment::factory()->recorded()->create();
    $svc = app(PaymentService::class);

    $first  = $svc->confirmPayment($payment, 'evt-idempotent');
    $second = $svc->confirmPayment($first,   'evt-idempotent');

    expect($first->id)->toBe($second->id);
});

it('rejects confirming a non-RECORDED payment', function () {
    $payment = Payment::factory()->create(['status' => PaymentStatus::VOIDED->value]);

    expect(fn () => app(PaymentService::class)->confirmPayment($payment, 'evt-xyz'))
        ->toThrow(RuntimeException::class);
});

it('transitions linked membership order to PAID on confirm', function () {
    $user    = User::factory()->create();
    $payment = Payment::factory()->recorded()->for($user)->create();
    $plan    = MembershipPlan::factory()->basic()->create();
    $order   = MembershipOrder::factory()->for($user)->for($plan, 'plan')->create([
        'payment_id' => $payment->id,
        'status'     => OrderStatus::PENDING->value,
    ]);

    app(PaymentService::class)->confirmPayment($payment, 'evt-wire');

    expect($order->fresh()->status)->toBe(OrderStatus::PAID);
});

// ── FIN-03: Void ───────────────────────────────────────────────────────────────

it('voids a RECORDED payment', function () {
    $payment = Payment::factory()->recorded()->create();

    $voided = app(PaymentService::class)->voidPayment($payment);

    expect($voided->status)->toBe(PaymentStatus::VOIDED);
});

it('voids a CONFIRMED payment', function () {
    $payment = Payment::factory()->confirmed()->create();

    $voided = app(PaymentService::class)->voidPayment($payment);

    expect($voided->status)->toBe(PaymentStatus::VOIDED);
});

it('rejects voiding an already-voided payment', function () {
    $payment = Payment::factory()->create(['status' => PaymentStatus::VOIDED->value]);

    expect(fn () => app(PaymentService::class)->voidPayment($payment))
        ->toThrow(RuntimeException::class);
});

it('voids linked membership order on void', function () {
    $user    = User::factory()->create();
    $payment = Payment::factory()->recorded()->for($user)->create();
    $plan    = MembershipPlan::factory()->basic()->create();
    $order   = MembershipOrder::factory()->for($user)->for($plan, 'plan')->create([
        'payment_id' => $payment->id,
        'status'     => OrderStatus::PENDING->value,
    ]);

    app(PaymentService::class)->voidPayment($payment);

    expect($order->fresh()->status)->toBe(OrderStatus::VOIDED);
});
