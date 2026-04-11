<?php

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\RefundStatus;
use App\Enums\RefundType;
use App\Models\MembershipOrder;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Services\MembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

function mkIdem(): string { return (string) Str::uuid(); }

// ── MEM-01: Purchase ──────────────────────────────────────────────────────────

it('creates a PENDING purchase order for a user with no active membership', function () {
    $user = User::factory()->create();
    $plan = MembershipPlan::factory()->basic()->create();

    $order = app(MembershipService::class)->purchase($user, $plan, mkIdem());

    expect($order->status)->toBe(OrderStatus::PENDING)
        ->and($order->order_type)->toBe(OrderType::PURCHASE)
        ->and($order->amount_cents)->toBe($plan->price_cents)
        ->and($order->top_up_eligible_until)->not->toBeNull();
});

it('blocks purchase when user already has an active membership', function () {
    $user = User::factory()->create();
    $plan = MembershipPlan::factory()->basic()->create();
    MembershipOrder::factory()->active()->for($user)->for($plan, 'plan')->create();

    expect(fn () => app(MembershipService::class)->purchase($user, $plan, mkIdem()))
        ->toThrow(RuntimeException::class, 'active membership');
});

it('blocks purchase for an inactive plan', function () {
    $user = User::factory()->create();
    $plan = MembershipPlan::factory()->inactive()->create();

    expect(fn () => app(MembershipService::class)->purchase($user, $plan, mkIdem()))
        ->toThrow(RuntimeException::class, 'no longer available');
});

it('returns existing order when idempotency key is reused', function () {
    $user = User::factory()->create();
    $plan = MembershipPlan::factory()->basic()->create();
    $k    = mkIdem();

    $first  = app(MembershipService::class)->purchase($user, $plan, $k);
    $second = app(MembershipService::class)->purchase($user, $plan, $k);

    expect($first->id)->toBe($second->id);
});

// ── MEM-02: Renew ─────────────────────────────────────────────────────────────

it('creates a renewal order extending from current expiry', function () {
    $user    = User::factory()->create();
    $plan    = MembershipPlan::factory()->basic()->create(['duration_months' => 12]);
    $current = MembershipOrder::factory()->active()->for($user)->for($plan, 'plan')->create();

    $renewal = app(MembershipService::class)->renew($user, $plan, mkIdem());

    expect($renewal->order_type)->toBe(OrderType::RENEWAL)
        ->and($renewal->expires_at->gte($current->expires_at))->toBeTrue();
});

it('creates a renewal order starting from now when current is expired', function () {
    $user = User::factory()->create();
    $plan = MembershipPlan::factory()->basic()->create(['duration_months' => 6]);
    MembershipOrder::factory()->expired()->for($user)->for($plan, 'plan')->create();

    $renewal = app(MembershipService::class)->renew($user, $plan, mkIdem());

    expect($renewal->order_type)->toBe(OrderType::RENEWAL)
        ->and($renewal->expires_at->isFuture())->toBeTrue()
        ->and($renewal->expires_at->gt(now()->addMonths(5)))->toBeTrue();
});

// ── MEM-03 / MEM-04: Top-up ───────────────────────────────────────────────────

it('creates a top-up order with price difference and keeps original expiry', function () {
    $user    = User::factory()->create();
    $basic   = MembershipPlan::factory()->basic()->create(['price_cents' => 4900]);
    $premium = MembershipPlan::factory()->premium()->create(['price_cents' => 19900]);
    $current = MembershipOrder::factory()->active()->for($user)->for($basic, 'plan')->create();

    $topUp = app(MembershipService::class)->topUp($user, $premium, mkIdem());

    expect($topUp->order_type)->toBe(OrderType::TOP_UP)
        ->and($topUp->amount_cents)->toBe(15000)           // 199 - 49 = 150
        ->and($topUp->expires_at->equalTo($current->expires_at))->toBeTrue()
        ->and($topUp->top_up_eligible_until)->toBeNull();  // no further top-ups
});

it('rejects top-up when no active membership', function () {
    $user    = User::factory()->create();
    $premium = MembershipPlan::factory()->premium()->create();

    expect(fn () => app(MembershipService::class)->topUp($user, $premium, mkIdem()))
        ->toThrow(RuntimeException::class, 'active membership');
});

it('rejects top-up after 30-day window', function () {
    $user  = User::factory()->create();
    $basic = MembershipPlan::factory()->basic()->create();
    $prem  = MembershipPlan::factory()->premium()->create();
    MembershipOrder::factory()->active()->for($user)->for($basic, 'plan')->create([
        'top_up_eligible_until' => now()->subDay(),
    ]);

    expect(fn () => app(MembershipService::class)->topUp($user, $prem, mkIdem()))
        ->toThrow(RuntimeException::class, '30-day upgrade window');
});

it('rejects downgrade in top-up', function () {
    $user  = User::factory()->create();
    $prem  = MembershipPlan::factory()->premium()->create();
    $basic = MembershipPlan::factory()->basic()->create();
    MembershipOrder::factory()->active()->for($user)->for($prem, 'plan')->create();

    expect(fn () => app(MembershipService::class)->topUp($user, $basic, mkIdem()))
        ->toThrow(RuntimeException::class, 'downgrade');
});

// ── MEM-05: Request Refund ────────────────────────────────────────────────────

it('creates a PENDING refund for a PAID order', function () {
    $user    = User::factory()->create();
    $plan    = MembershipPlan::factory()->basic()->create();
    $payment = Payment::factory()->for($user)->create(['amount_cents' => 4900]);
    $order   = MembershipOrder::factory()->paid()->for($user)->for($plan, 'plan')->create([
        'payment_id'   => $payment->id,
        'amount_cents' => 4900,
    ]);

    $refund = app(MembershipService::class)->requestRefund(
        $order, RefundType::FULL, 'I changed my mind about this plan.', null, mkIdem()
    );

    expect($refund->status)->toBe(RefundStatus::PENDING)
        ->and($refund->amount_cents)->toBe(4900)
        ->and($refund->refund_type)->toBe(RefundType::FULL);
});

it('rejects refund for non-PAID order', function () {
    $plan  = MembershipPlan::factory()->basic()->create();
    $order = MembershipOrder::factory()->pending()->for(User::factory())->for($plan, 'plan')->create();

    expect(fn () => app(MembershipService::class)->requestRefund(
        $order, RefundType::FULL, 'reason here please', null, mkIdem()
    ))->toThrow(RuntimeException::class, 'Only PAID orders');
});

it('rejects refund when order has no payment_id', function () {
    $user  = User::factory()->create();
    $plan  = MembershipPlan::factory()->basic()->create();
    $order = MembershipOrder::factory()->for($user)->for($plan, 'plan')->create([
        'status'     => 'PAID',
        'payment_id' => null,
    ]);

    expect(fn () => app(MembershipService::class)->requestRefund(
        $order, RefundType::FULL, 'reason here please', null, mkIdem()
    ))->toThrow(RuntimeException::class, 'no linked payment');
});

it('rejects partial refund with amount >= order total', function () {
    $user    = User::factory()->create();
    $plan    = MembershipPlan::factory()->basic()->create();
    $payment = Payment::factory()->for($user)->create(['amount_cents' => 4900]);
    $order   = MembershipOrder::factory()->paid()->for($user)->for($plan, 'plan')->create([
        'payment_id'   => $payment->id,
        'amount_cents' => 4900,
    ]);

    expect(fn () => app(MembershipService::class)->requestRefund(
        $order, RefundType::PARTIAL, 'reason here too please', 4900, mkIdem()
    ))->toThrow(RuntimeException::class, 'less than the order total');
});

it('rejects refund reason shorter than 10 characters', function () {
    $user    = User::factory()->create();
    $plan    = MembershipPlan::factory()->basic()->create();
    $payment = Payment::factory()->for($user)->create(['amount_cents' => 4900]);
    $order   = MembershipOrder::factory()->paid()->for($user)->for($plan, 'plan')->create([
        'payment_id'   => $payment->id,
        'amount_cents' => 4900,
    ]);

    expect(fn () => app(MembershipService::class)->requestRefund(
        $order, RefundType::FULL, 'short', null, mkIdem()
    ))->toThrow(RuntimeException::class, 'at least 10 characters');
});

// ── MEM-06: Approve Refund ────────────────────────────────────────────────────

it('transitions refund from PENDING to APPROVED', function () {
    $approver = User::factory()->create();
    $refund   = Refund::factory()->pending()->create();

    $result = app(MembershipService::class)->approveRefund($refund, $approver, mkIdem());

    expect($result->status)->toBe(RefundStatus::APPROVED)
        ->and((string) $result->approved_by)->toBe((string) $approver->id);
});

it('rejects approving a non-PENDING refund', function () {
    $approver = User::factory()->create();
    $refund   = Refund::factory()->approved()->create();

    expect(fn () => app(MembershipService::class)->approveRefund($refund, $approver, mkIdem()))
        ->toThrow(RuntimeException::class, 'Only PENDING refunds');
});

it('approveRefund is idempotent on the same key', function () {
    $approver = User::factory()->create();
    $refund   = Refund::factory()->pending()->create();
    $key      = mkIdem();
    $svc      = app(MembershipService::class);

    $first  = $svc->approveRefund($refund, $approver, $key);
    $second = $svc->approveRefund($refund->fresh(), $approver, $key);

    expect($first->id)->toBe($second->id)
        ->and($second->status)->toBe(RefundStatus::APPROVED);
});

// ── MEM-07: Process Refund ────────────────────────────────────────────────────

it('full refund sets order to REFUNDED and terminates membership', function () {
    $user    = User::factory()->create();
    $plan    = MembershipPlan::factory()->basic()->create();
    $payment = Payment::factory()->for($user)->create(['amount_cents' => 4900]);
    $order   = MembershipOrder::factory()->active()->for($user)->for($plan, 'plan')->create([
        'payment_id'   => $payment->id,
        'amount_cents' => 4900,
    ]);
    $refund  = Refund::factory()->approved()->create([
        'payment_id'  => $payment->id,
        'amount_cents'=> 4900,
        'refund_type' => RefundType::FULL->value,
    ]);

    app(MembershipService::class)->processRefund($refund, mkIdem());

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::REFUNDED)
        ->and($order->expires_at->isPast())->toBeTrue();
});

it('partial refund sets order to PARTIALLY_REFUNDED and keeps membership active', function () {
    $user    = User::factory()->create();
    $plan    = MembershipPlan::factory()->basic()->create();
    $payment = Payment::factory()->for($user)->create(['amount_cents' => 4900]);
    $order   = MembershipOrder::factory()->active()->for($user)->for($plan, 'plan')->create([
        'payment_id'   => $payment->id,
        'amount_cents' => 4900,
    ]);
    $refund  = Refund::factory()->approved()->create([
        'payment_id'  => $payment->id,
        'amount_cents'=> 2000,
        'refund_type' => RefundType::PARTIAL->value,
    ]);

    app(MembershipService::class)->processRefund($refund, mkIdem());

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::PARTIALLY_REFUNDED)
        ->and($order->expires_at->isFuture())->toBeTrue();
});

it('rejects processing a non-APPROVED refund', function () {
    $refund = Refund::factory()->pending()->create();

    expect(fn () => app(MembershipService::class)->processRefund($refund, mkIdem()))
        ->toThrow(RuntimeException::class, 'Only APPROVED refunds');
});

it('processRefund is idempotent on the same key', function () {
    $user    = User::factory()->create();
    $plan    = MembershipPlan::factory()->basic()->create();
    $payment = Payment::factory()->for($user)->create(['amount_cents' => 4900]);
    MembershipOrder::factory()->active()->for($user)->for($plan, 'plan')->create([
        'payment_id'   => $payment->id,
        'amount_cents' => 4900,
    ]);
    $refund = Refund::factory()->approved()->create([
        'payment_id'  => $payment->id,
        'amount_cents'=> 2000,
        'refund_type' => RefundType::PARTIAL->value,
    ]);
    $key = mkIdem();
    $svc = app(MembershipService::class);

    $first  = $svc->processRefund($refund, $key);
    $second = $svc->processRefund($refund->fresh(), $key);

    expect($first->id)->toBe($second->id)
        ->and($second->status)->toBe(RefundStatus::PROCESSED);
});
