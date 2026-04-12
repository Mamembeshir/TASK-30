<?php

use App\Enums\InvoiceStatus;
use App\Enums\LineItemType;
use App\Enums\UserRole;
use App\Models\Invoice;
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

function invoiceFinanceUser(): User
{
    $user = User::factory()->create();
    UserProfile::create(['user_id' => $user->id, 'first_name' => 'Finance', 'last_name' => 'Staff']);
    $user->addRole(UserRole::FINANCE_SPECIALIST);
    return $user->fresh();
}

function invoicePlainMember(): User
{
    $user = User::factory()->create();
    UserProfile::create(['user_id' => $user->id, 'first_name' => 'Plain', 'last_name' => 'Member']);
    $user->addRole(UserRole::MEMBER);
    return $user->fresh();
}

// ── POST /api/invoices ────────────────────────────────────────────────────────

it('POST /api/invoices finance user creates invoice (201)', function () {
    $finance = invoiceFinanceUser();
    $target  = User::factory()->create();

    $this->actingAs($finance)
        ->postJson('/api/invoices', [
            'user_id'         => $target->id,
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertCreated()
        ->assertJsonPath('status', InvoiceStatus::DRAFT->value);
});

it('POST /api/invoices is idempotent on same key', function () {
    $finance = invoiceFinanceUser();
    $target  = User::factory()->create();
    $key     = (string) Str::uuid();

    $r1 = $this->actingAs($finance)
        ->postJson('/api/invoices', [
            'user_id'         => $target->id,
            'idempotency_key' => $key,
        ])
        ->assertCreated();

    $r2 = $this->actingAs($finance)
        ->postJson('/api/invoices', [
            'user_id'         => $target->id,
            'idempotency_key' => $key,
        ])
        ->assertCreated();

    expect($r1->json('id'))->toBe($r2->json('id'));
});

it('POST /api/invoices returns 403 for plain member', function () {
    $member = invoicePlainMember();
    $target = User::factory()->create();

    $this->actingAs($member)
        ->postJson('/api/invoices', [
            'user_id'         => $target->id,
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertForbidden();
});

it('POST /api/invoices returns 422 on missing user_id', function () {
    $finance = invoiceFinanceUser();

    $this->actingAs($finance)
        ->postJson('/api/invoices', [
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('user_id');
});

it('POST /api/invoices returns 422 on missing idempotency_key', function () {
    $finance = invoiceFinanceUser();
    $target  = User::factory()->create();

    $this->actingAs($finance)
        ->postJson('/api/invoices', [
            'user_id' => $target->id,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('idempotency_key');
});

// ── POST /api/invoices/{invoice}/lines ────────────────────────────────────────

it('POST /api/invoices/{invoice}/lines adds a line item (201)', function () {
    $finance = invoiceFinanceUser();
    $invoice = Invoice::factory()->create();

    $this->actingAs($finance)
        ->postJson("/api/invoices/{$invoice->id}/lines", [
            'description'  => 'Trip signup fee',
            'amount_cents' => 15000,
            'line_type'    => LineItemType::TRIP_SIGNUP->value,
        ])
        ->assertCreated()
        ->assertJsonPath('amount_cents', 15000);
});

it('POST /api/invoices/{invoice}/lines returns 403 for plain member', function () {
    $member  = invoicePlainMember();
    $invoice = Invoice::factory()->create();

    $this->actingAs($member)
        ->postJson("/api/invoices/{$invoice->id}/lines", [
            'description'  => 'Trip signup fee',
            'amount_cents' => 15000,
            'line_type'    => LineItemType::TRIP_SIGNUP->value,
        ])
        ->assertForbidden();
});

it('POST /api/invoices/{invoice}/lines returns 422 when invoice is not DRAFT', function () {
    $finance = invoiceFinanceUser();
    $invoice = Invoice::factory()->issued()->create();

    $this->actingAs($finance)
        ->postJson("/api/invoices/{$invoice->id}/lines", [
            'description'  => 'Trip signup fee',
            'amount_cents' => 15000,
            'line_type'    => LineItemType::TRIP_SIGNUP->value,
        ])
        ->assertStatus(422);
});

it('POST /api/invoices/{invoice}/lines returns 422 on missing description', function () {
    $finance = invoiceFinanceUser();
    $invoice = Invoice::factory()->create();

    $this->actingAs($finance)
        ->postJson("/api/invoices/{$invoice->id}/lines", [
            'amount_cents' => 15000,
            'line_type'    => LineItemType::TRIP_SIGNUP->value,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('description');
});

// ── POST /api/invoices/{invoice}/issue ────────────────────────────────────────

it('POST /api/invoices/{invoice}/issue transitions DRAFT to ISSUED (200)', function () {
    $finance = invoiceFinanceUser();
    $invoice = Invoice::factory()->create();

    $this->actingAs($finance)
        ->postJson("/api/invoices/{$invoice->id}/issue")
        ->assertOk()
        ->assertJsonPath('status', InvoiceStatus::ISSUED->value);
});

it('POST /api/invoices/{invoice}/issue returns 403 for plain member', function () {
    $member  = invoicePlainMember();
    $invoice = Invoice::factory()->create();

    $this->actingAs($member)
        ->postJson("/api/invoices/{$invoice->id}/issue")
        ->assertForbidden();
});

it('POST /api/invoices/{invoice}/issue returns 422 when already ISSUED', function () {
    $finance = invoiceFinanceUser();
    $invoice = Invoice::factory()->issued()->create();

    $this->actingAs($finance)
        ->postJson("/api/invoices/{$invoice->id}/issue")
        ->assertStatus(422);
});

// ── POST /api/invoices/{invoice}/mark-paid ────────────────────────────────────

it('POST /api/invoices/{invoice}/mark-paid transitions ISSUED to PAID (200)', function () {
    $finance = invoiceFinanceUser();
    $invoice = Invoice::factory()->issued()->create();

    $this->actingAs($finance)
        ->postJson("/api/invoices/{$invoice->id}/mark-paid")
        ->assertOk()
        ->assertJsonPath('status', InvoiceStatus::PAID->value);
});

it('POST /api/invoices/{invoice}/mark-paid returns 422 when invoice is in DRAFT state', function () {
    $finance = invoiceFinanceUser();
    $invoice = Invoice::factory()->create(); // default: DRAFT

    $this->actingAs($finance)
        ->postJson("/api/invoices/{$invoice->id}/mark-paid")
        ->assertStatus(422);
});

// ── POST /api/invoices/{invoice}/void ─────────────────────────────────────────

it('POST /api/invoices/{invoice}/void voids a DRAFT invoice (200)', function () {
    $finance = invoiceFinanceUser();
    $invoice = Invoice::factory()->create();

    $this->actingAs($finance)
        ->postJson("/api/invoices/{$invoice->id}/void")
        ->assertOk()
        ->assertJsonPath('status', InvoiceStatus::VOIDED->value);
});

it('POST /api/invoices/{invoice}/void voids an ISSUED invoice (200)', function () {
    $finance = invoiceFinanceUser();
    $invoice = Invoice::factory()->issued()->create();

    $this->actingAs($finance)
        ->postJson("/api/invoices/{$invoice->id}/void")
        ->assertOk()
        ->assertJsonPath('status', InvoiceStatus::VOIDED->value);
});

it('POST /api/invoices/{invoice}/void returns 422 when invoice is already PAID', function () {
    $finance = invoiceFinanceUser();
    $invoice = Invoice::factory()->paid()->create();

    $this->actingAs($finance)
        ->postJson("/api/invoices/{$invoice->id}/void")
        ->assertStatus(422);
});
