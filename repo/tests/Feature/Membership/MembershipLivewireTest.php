<?php

use App\Enums\OrderStatus;
use App\Enums\RefundStatus;
use App\Enums\UserRole as UserRoleEnum;
use App\Enums\UserStatus;
use App\Livewire\Membership\MyMembership;
use App\Livewire\Membership\PlanCatalog;
use App\Livewire\Membership\PurchaseFlow;
use App\Livewire\Membership\RefundApproval;
use App\Livewire\Membership\RefundRequest;
use App\Livewire\Membership\TopUpFlow;
use App\Models\MembershipOrder;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ── PlanCatalog ───────────────────────────────────────────────────────────────

it('renders active plans in plan catalog', function () {
    $user = User::factory()->create(['status' => UserStatus::ACTIVE]);
    MembershipPlan::factory()->basic()->create(['name' => 'Basic Plan']);
    MembershipPlan::factory()->premium()->create(['name' => 'Premium Plan']);

    Livewire::actingAs($user)
        ->test(PlanCatalog::class)
        ->assertSee('Basic Plan')
        ->assertSee('Premium Plan');
});

it('does not show inactive plans in catalog', function () {
    $user = User::factory()->create(['status' => UserStatus::ACTIVE]);
    MembershipPlan::factory()->inactive()->create(['name' => 'Old Plan']);

    Livewire::actingAs($user)
        ->test(PlanCatalog::class)
        ->assertDontSee('Old Plan');
});

it('shows Purchase button when user has no active membership', function () {
    $user = User::factory()->create(['status' => UserStatus::ACTIVE]);
    MembershipPlan::factory()->basic()->create();

    Livewire::actingAs($user)
        ->test(PlanCatalog::class)
        ->assertSee('Purchase');
});

it('shows Current Plan badge for active plan', function () {
    $user = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $plan = MembershipPlan::factory()->basic()->create();
    MembershipOrder::factory()->active()->for($user)->for($plan, 'plan')->create();

    Livewire::actingAs($user)
        ->test(PlanCatalog::class)
        ->assertSee('Current Plan');
});

// ── MyMembership ──────────────────────────────────────────────────────────────

it('shows active membership details', function () {
    $user = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $plan = MembershipPlan::factory()->basic()->create(['name' => 'Basic Plan']);
    MembershipOrder::factory()->active()->for($user)->for($plan, 'plan')->create();

    Livewire::actingAs($user)
        ->test(MyMembership::class)
        ->assertSee('Basic Plan')
        ->assertSee('Active');
});

it('shows no active membership when none exists', function () {
    $user = User::factory()->create(['status' => UserStatus::ACTIVE]);

    Livewire::actingAs($user)
        ->test(MyMembership::class)
        ->assertSee('You do not have an active membership');
});

it('shows refund button for PAID orders', function () {
    $user    = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $plan    = MembershipPlan::factory()->basic()->create();
    $payment = Payment::factory()->for($user)->create();
    MembershipOrder::factory()->active()->for($user)->for($plan, 'plan')->create([
        'payment_id' => $payment->id,
    ]);

    Livewire::actingAs($user)
        ->test(MyMembership::class)
        ->assertSee('Request Refund');
});

// ── PurchaseFlow ──────────────────────────────────────────────────────────────

it('shows plan details on purchase flow', function () {
    $user = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $plan = MembershipPlan::factory()->basic()->create(['name' => 'Basic Plan']);

    Livewire::actingAs($user)
        ->test(PurchaseFlow::class, ['plan' => $plan])
        ->assertSee('Basic Plan')
        ->assertSee('Confirm Order');
});

it('confirms order then places it', function () {
    $user = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $plan = MembershipPlan::factory()->basic()->create();

    Livewire::actingAs($user)
        ->test(PurchaseFlow::class, ['plan' => $plan])
        ->call('confirm')
        ->assertSet('confirmed', true)
        ->call('submit')
        ->assertRedirect(route('membership.my'));

    expect(MembershipOrder::where('user_id', $user->id)->where('status', OrderStatus::PENDING)->exists())
        ->toBeTrue();
});

it('shows error when purchasing with active membership', function () {
    $user = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $plan = MembershipPlan::factory()->basic()->create();
    MembershipOrder::factory()->active()->for($user)->for($plan, 'plan')->create();

    $component = Livewire::actingAs($user)
        ->test(PurchaseFlow::class, ['plan' => $plan])
        ->call('confirm')
        ->call('submit');

    expect($component->get('error'))->toContain('active membership');
});

// ── TopUpFlow ─────────────────────────────────────────────────────────────────

it('shows current and new plan details on top-up flow', function () {
    $user    = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $basic   = MembershipPlan::factory()->basic()->create(['name' => 'Basic Plan']);
    $premium = MembershipPlan::factory()->premium()->create(['name' => 'Premium Plan']);
    MembershipOrder::factory()->active()->for($user)->for($basic, 'plan')->create();

    Livewire::actingAs($user)
        ->test(TopUpFlow::class, ['plan' => $premium])
        ->assertSee('Basic Plan')
        ->assertSee('Premium Plan')
        ->assertSee('Confirm Upgrade');
});

// ── RefundRequest ─────────────────────────────────────────────────────────────

it('renders refund request form', function () {
    $user    = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $plan    = MembershipPlan::factory()->basic()->create();
    $payment = Payment::factory()->for($user)->create();
    $order   = MembershipOrder::factory()->active()->for($user)->for($plan, 'plan')->create([
        'payment_id' => $payment->id,
    ]);

    Livewire::actingAs($user)
        ->test(RefundRequest::class, ['order' => $order])
        ->assertSee('Request Refund')
        ->assertSee('Full Refund')
        ->assertSee('Partial Refund');
});

it('submits a full refund request', function () {
    $user    = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $plan    = MembershipPlan::factory()->basic()->create();
    $payment = Payment::factory()->for($user)->create(['amount_cents' => 4900]);
    $order   = MembershipOrder::factory()->paid()->for($user)->for($plan, 'plan')->create([
        'payment_id'   => $payment->id,
        'amount_cents' => 4900,
    ]);

    Livewire::actingAs($user)
        ->test(RefundRequest::class, ['order' => $order])
        ->set('refundType', 'FULL')
        ->set('reason', 'I no longer need this membership plan.')
        ->call('submit')
        ->assertRedirect(route('membership.my'));

    expect(Refund::where('payment_id', $payment->id)->where('status', RefundStatus::PENDING)->exists())
        ->toBeTrue();
});

it('validates reason length on refund request', function () {
    $user    = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $plan    = MembershipPlan::factory()->basic()->create();
    $payment = Payment::factory()->for($user)->create(['amount_cents' => 4900]);
    $order   = MembershipOrder::factory()->paid()->for($user)->for($plan, 'plan')->create([
        'payment_id'   => $payment->id,
        'amount_cents' => 4900,
    ]);

    Livewire::actingAs($user)
        ->test(RefundRequest::class, ['order' => $order])
        ->set('reason', 'Short')
        ->call('submit')
        ->assertHasErrors(['reason']);
});

// ── RefundApproval ────────────────────────────────────────────────────────────

it('renders refund approval for finance specialist', function () {
    $finance = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $finance->addRole(UserRoleEnum::FINANCE_SPECIALIST);

    $refund = Refund::factory()->pending()->create();

    Livewire::actingAs($finance)
        ->test(RefundApproval::class)
        ->assertOk();
});

it('finance specialist can approve a pending refund', function () {
    $finance = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $finance->addRole(UserRoleEnum::FINANCE_SPECIALIST);

    $refund = Refund::factory()->pending()->create();

    Livewire::actingAs($finance)
        ->test(RefundApproval::class)
        ->call('approve', $refund->id);

    expect($refund->fresh()->status)->toBe(RefundStatus::APPROVED);
});

it('finance specialist can process an approved refund', function () {
    $finance = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $finance->addRole(UserRoleEnum::FINANCE_SPECIALIST);

    $refund = Refund::factory()->approved()->create();

    Livewire::actingAs($finance)
        ->test(RefundApproval::class)
        ->call('process', $refund->id);

    expect($refund->fresh()->status)->toBe(RefundStatus::PROCESSED);
});

it('non-finance user cannot access refund approval', function () {
    $user = User::factory()->create(['status' => UserStatus::ACTIVE]);

    $this->actingAs($user)
         ->get(route('finance.refunds'))
         ->assertForbidden();
});
