<?php

use App\Enums\RefundStatus;
use App\Enums\RefundType;
use App\Enums\UserRole;
use App\Models\MembershipOrder;
use App\Models\MembershipPlan;
use App\Models\Refund;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

// All mutation requests must include a same-origin Origin header so that
// VerifyApiCsrfToken grants the JSON exemption (mirrors real browser behaviour).
beforeEach(function () {
    $this->withHeaders(['Origin' => config('app.url')]);
});

// ── Helpers ───────────────────────────────────────────────────────────────────

function membershipMember(): User
{
    $user = User::factory()->create();
    UserProfile::create(['user_id' => $user->id, 'first_name' => 'Test', 'last_name' => 'Member']);
    $user->addRole(UserRole::MEMBER);
    return $user->fresh();
}

function membershipFinanceUser(): User
{
    $user = User::factory()->create();
    UserProfile::create(['user_id' => $user->id, 'first_name' => 'Finance', 'last_name' => 'Staff']);
    $user->addRole(UserRole::FINANCE_SPECIALIST);
    return $user->fresh();
}

// ── POST /api/membership/plans/{plan}/purchase ────────────────────────────────

it('POST /api/membership/plans/{plan}/purchase creates an order for a member with no active membership', function () {
    $member = membershipMember();
    $plan   = MembershipPlan::factory()->basic()->create();

    $this->actingAs($member)
        ->postJson("/api/membership/plans/{$plan->id}/purchase", [
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertCreated();
});

it('POST /api/membership/plans/{plan}/purchase is idempotent on same key', function () {
    $member = membershipMember();
    $plan   = MembershipPlan::factory()->standard()->create();
    $key    = (string) Str::uuid();

    $r1 = $this->actingAs($member)
        ->postJson("/api/membership/plans/{$plan->id}/purchase", [
            'idempotency_key' => $key,
        ])
        ->assertCreated();

    $r2 = $this->actingAs($member)
        ->postJson("/api/membership/plans/{$plan->id}/purchase", [
            'idempotency_key' => $key,
        ])
        ->assertCreated();

    expect($r1->json('id'))->toBe($r2->json('id'));
});

it('POST /api/membership/plans/{plan}/purchase returns 422 when plan is inactive', function () {
    $member = membershipMember();
    $plan   = MembershipPlan::factory()->inactive()->create();

    $this->actingAs($member)
        ->postJson("/api/membership/plans/{$plan->id}/purchase", [
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertStatus(422);
});

it('POST /api/membership/plans/{plan}/purchase returns 401 when unauthenticated', function () {
    $plan = MembershipPlan::factory()->basic()->create();

    $this->postJson("/api/membership/plans/{$plan->id}/purchase", [
        'idempotency_key' => (string) Str::uuid(),
    ])->assertUnauthorized();
});

// ── POST /api/membership/plans/{plan}/top-up ──────────────────────────────────

it('POST /api/membership/plans/{plan}/top-up succeeds when member has active membership', function () {
    $member    = membershipMember();
    $basicPlan = MembershipPlan::factory()->basic()->create();
    MembershipOrder::factory()->active()->create([
        'user_id' => $member->id,
        'plan_id' => $basicPlan->id,
    ]);
    $plan = MembershipPlan::factory()->premium()->create();

    $this->actingAs($member)
        ->postJson("/api/membership/plans/{$plan->id}/top-up", [
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertCreated();
});

it('POST /api/membership/plans/{plan}/top-up returns 422 when member has no active membership', function () {
    $member = membershipMember();
    $plan   = MembershipPlan::factory()->premium()->create();

    $this->actingAs($member)
        ->postJson("/api/membership/plans/{$plan->id}/top-up", [
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertStatus(422);
});

// ── POST /api/membership/orders/{order}/refund ────────────────────────────────

it('POST /api/membership/orders/{order}/refund allows member to request a FULL refund on their own order', function () {
    $member = membershipMember();
    $order  = MembershipOrder::factory()->paid()->create(['user_id' => $member->id]);

    $this->actingAs($member)
        ->postJson("/api/membership/orders/{$order->id}/refund", [
            'refund_type' => RefundType::FULL->value,
            'reason'      => 'I would like to cancel my membership subscription.',
        ])
        ->assertCreated();
});

it('POST /api/membership/orders/{order}/refund allows member to request a PARTIAL refund with amount_cents', function () {
    $member = membershipMember();
    $order  = MembershipOrder::factory()->paid()->create(['user_id' => $member->id]);

    $this->actingAs($member)
        ->postJson("/api/membership/orders/{$order->id}/refund", [
            'refund_type'  => RefundType::PARTIAL->value,
            'reason'       => 'I only used part of my membership period.',
            'amount_cents' => 2500,
        ])
        ->assertCreated();
});

it('POST /api/membership/orders/{order}/refund returns 403 when caller does not own the order', function () {
    $member      = membershipMember();
    $otherMember = membershipMember();
    $order       = MembershipOrder::factory()->paid()->create(['user_id' => $otherMember->id]);

    $this->actingAs($member)
        ->postJson("/api/membership/orders/{$order->id}/refund", [
            'refund_type' => RefundType::FULL->value,
            'reason'      => 'I would like to cancel my membership subscription.',
        ])
        ->assertForbidden();
});

it('POST /api/membership/orders/{order}/refund returns 422 when reason is too short', function () {
    $member = membershipMember();
    $order  = MembershipOrder::factory()->paid()->create(['user_id' => $member->id]);

    $this->actingAs($member)
        ->postJson("/api/membership/orders/{$order->id}/refund", [
            'refund_type' => RefundType::FULL->value,
            'reason'      => 'Short', // under min:10
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('reason');
});

it('POST /api/membership/orders/{order}/refund returns 422 when PARTIAL refund is missing amount_cents', function () {
    $member = membershipMember();
    $order  = MembershipOrder::factory()->paid()->create(['user_id' => $member->id]);

    $this->actingAs($member)
        ->postJson("/api/membership/orders/{$order->id}/refund", [
            'refund_type' => RefundType::PARTIAL->value,
            'reason'      => 'I only used part of my membership period.',
            // intentionally no amount_cents
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('amount_cents');
});

// ── POST /api/membership/refunds/{refund}/approve ─────────────────────────────

it('POST /api/membership/refunds/{refund}/approve allows finance to approve a PENDING refund', function () {
    $finance = membershipFinanceUser();
    $refund  = Refund::factory()->pending()->create();

    $this->actingAs($finance)
        ->postJson("/api/membership/refunds/{$refund->id}/approve")
        ->assertOk()
        ->assertJsonPath('status', RefundStatus::APPROVED->value);
});

it('POST /api/membership/refunds/{refund}/approve returns 403 for plain member', function () {
    $member = membershipMember();
    $refund = Refund::factory()->pending()->create();

    $this->actingAs($member)
        ->postJson("/api/membership/refunds/{$refund->id}/approve")
        ->assertForbidden();
});

it('POST /api/membership/refunds/{refund}/approve returns 422 when refund is not PENDING', function () {
    $finance = membershipFinanceUser();
    $refund  = Refund::factory()->approved()->create();

    $this->actingAs($finance)
        ->postJson("/api/membership/refunds/{$refund->id}/approve")
        ->assertStatus(422);
});

// ── POST /api/membership/refunds/{refund}/process ─────────────────────────────

it('POST /api/membership/refunds/{refund}/process allows finance to process an APPROVED refund', function () {
    $finance = membershipFinanceUser();
    $refund  = Refund::factory()->approved()->create();

    $this->actingAs($finance)
        ->postJson("/api/membership/refunds/{$refund->id}/process")
        ->assertOk()
        ->assertJsonPath('status', RefundStatus::PROCESSED->value);
});

it('POST /api/membership/refunds/{refund}/process returns 422 when refund is not APPROVED', function () {
    $finance = membershipFinanceUser();
    $refund  = Refund::factory()->pending()->create();

    $this->actingAs($finance)
        ->postJson("/api/membership/refunds/{$refund->id}/process")
        ->assertStatus(422);
});
