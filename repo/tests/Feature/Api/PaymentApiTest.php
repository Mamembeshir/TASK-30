<?php

use App\Enums\PaymentStatus;
use App\Enums\TenderType;
use App\Enums\UserRole;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

function apiFinanceUser(): User
{
    $user = User::factory()->create();
    UserProfile::create(['user_id' => $user->id, 'first_name' => 'Finance', 'last_name' => 'Staff']);
    $user->addRole(UserRole::FINANCE_SPECIALIST);
    return $user->fresh();
}

function apiPlainMember(): User
{
    $user = User::factory()->create();
    UserProfile::create(['user_id' => $user->id, 'first_name' => 'Plain', 'last_name' => 'Member']);
    $user->addRole(UserRole::MEMBER);
    return $user->fresh();
}

// ── POST /api/payments ────────────────────────────────────────────────────────

it('POST /api/payments records a payment for finance staff', function () {
    $finance = apiFinanceUser();
    $target  = User::factory()->create();

    $this->actingAs($finance)
        ->postJson('/api/payments', [
            'user_id'         => $target->id,
            'tender_type'     => TenderType::CASH->value,
            'amount_cents'    => 5000,
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertCreated()
        ->assertJsonPath('status', PaymentStatus::RECORDED->value)
        ->assertJsonPath('amount_cents', 5000);
});

it('POST /api/payments is idempotent on same key', function () {
    $finance = apiFinanceUser();
    $target  = User::factory()->create();
    $key     = (string) Str::uuid();

    $r1 = $this->actingAs($finance)
        ->postJson('/api/payments', [
            'user_id'         => $target->id,
            'tender_type'     => TenderType::CASH->value,
            'amount_cents'    => 5000,
            'idempotency_key' => $key,
        ])
        ->assertCreated();

    $r2 = $this->actingAs($finance)
        ->postJson('/api/payments', [
            'user_id'         => $target->id,
            'tender_type'     => TenderType::CASH->value,
            'amount_cents'    => 9999, // different amount — must be ignored
            'idempotency_key' => $key,
        ])
        ->assertCreated();

    expect($r1->json('id'))->toBe($r2->json('id'));
    expect($r2->json('amount_cents'))->toBe(5000); // original preserved
});

it('POST /api/payments returns 403 for plain member', function () {
    $member = apiPlainMember();
    $target = User::factory()->create();

    $this->actingAs($member)
        ->postJson('/api/payments', [
            'user_id'      => $target->id,
            'tender_type'  => TenderType::CASH->value,
            'amount_cents' => 5000,
        ])
        ->assertForbidden();
});

it('POST /api/payments returns 422 on missing required fields', function () {
    $finance = apiFinanceUser();

    $this->actingAs($finance)
        ->postJson('/api/payments', [])
        ->assertStatus(422);
});

// ── POST /api/payments/{payment}/confirm ──────────────────────────────────────

it('POST /api/payments/{payment}/confirm transitions RECORDED → CONFIRMED', function () {
    $finance = apiFinanceUser();
    $payment = Payment::factory()->recorded()->create();

    $this->actingAs($finance)
        ->postJson("/api/payments/{$payment->id}/confirm", [
            'confirmation_event_id' => 'evt-api-test-001',
        ])
        ->assertOk()
        ->assertJsonPath('status', PaymentStatus::CONFIRMED->value);
});

it('POST /api/payments/{payment}/confirm is idempotent on same event id', function () {
    $finance = apiFinanceUser();
    $payment = Payment::factory()->recorded()->create();

    $this->actingAs($finance)
        ->postJson("/api/payments/{$payment->id}/confirm", ['confirmation_event_id' => 'evt-idem'])
        ->assertOk();

    // Second call with same event id must not throw — returns current state
    $this->actingAs($finance)
        ->postJson("/api/payments/{$payment->id}/confirm", ['confirmation_event_id' => 'evt-idem'])
        ->assertOk()
        ->assertJsonPath('status', PaymentStatus::CONFIRMED->value);
});

// ── POST /api/payments/{payment}/void ────────────────────────────────────────

it('POST /api/payments/{payment}/void voids a RECORDED payment', function () {
    $finance = apiFinanceUser();
    $payment = Payment::factory()->recorded()->create();

    $this->actingAs($finance)
        ->postJson("/api/payments/{$payment->id}/void", [
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertOk()
        ->assertJsonPath('status', PaymentStatus::VOIDED->value);
});

it('POST /api/payments/{payment}/void returns 422 when already voided', function () {
    $finance = apiFinanceUser();
    $payment = Payment::factory()->create(['status' => PaymentStatus::VOIDED->value]);

    $this->actingAs($finance)
        ->postJson("/api/payments/{$payment->id}/void")
        ->assertStatus(422);
});

it('POST /api/payments/{payment}/void returns 401 when unauthenticated', function () {
    $payment = Payment::factory()->recorded()->create();

    $this->postJson("/api/payments/{$payment->id}/void")->assertUnauthorized();
});
