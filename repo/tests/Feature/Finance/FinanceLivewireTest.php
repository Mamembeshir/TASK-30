<?php

use App\Enums\ExceptionStatus;
use App\Enums\InvoiceStatus;
use App\Enums\LineItemType;
use App\Enums\PaymentStatus;
use App\Enums\SettlementStatus;
use App\Enums\TenderType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Livewire\Finance\FinanceDashboard;
use App\Livewire\Finance\InvoiceBuilder;
use App\Livewire\Finance\InvoiceDetail;
use App\Livewire\Finance\InvoiceIndex;
use App\Livewire\Finance\PaymentDetail;
use App\Livewire\Finance\PaymentIndex;
use App\Livewire\Finance\PaymentRecord;
use App\Livewire\Finance\SettlementDetail;
use App\Livewire\Finance\SettlementIndex;
use App\Livewire\Finance\StatementExport;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Settlement;
use App\Models\SettlementException;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ── Helpers ────────────────────────────────────────────────────────────────────

function mkSpecialist(): User
{
    $user = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $user->addRole(UserRole::FINANCE_SPECIALIST);
    return $user;
}

function mkAdmin(): User
{
    $user = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $user->addRole(UserRole::ADMIN);
    return $user;
}

// ── Access control ─────────────────────────────────────────────────────────────

it('denies non-finance users access to FinanceDashboard', function () {
    $member = User::factory()->create(['status' => UserStatus::ACTIVE]);

    Livewire::actingAs($member)
        ->test(FinanceDashboard::class)
        ->assertForbidden();
});

it('allows finance specialist access to FinanceDashboard', function () {
    Livewire::actingAs(mkSpecialist())
        ->test(FinanceDashboard::class)
        ->assertOk();
});

it('allows admin access to PaymentIndex', function () {
    Livewire::actingAs(mkAdmin())
        ->test(PaymentIndex::class)
        ->assertOk();
});

// ── PaymentRecord ──────────────────────────────────────────────────────────────

it('records a payment via PaymentRecord component', function () {
    $specialist = mkSpecialist();
    $member     = User::factory()->create();

    Livewire::actingAs($specialist)
        ->test(PaymentRecord::class)
        ->set('selectedUserId', $member->id)
        ->set('tenderType', TenderType::CASH->value)
        ->set('amountInput', '100.00')
        ->call('submit')
        ->assertRedirect();

    expect(Payment::where('user_id', $member->id)->exists())->toBeTrue();
});

it('validates required fields in PaymentRecord', function () {
    Livewire::actingAs(mkSpecialist())
        ->test(PaymentRecord::class)
        ->call('submit')
        ->assertHasErrors(['selectedUserId', 'tenderType', 'amountInput']);
});

// ── PaymentDetail ─────────────────────────────────────────────────────────────

it('confirms a payment via PaymentDetail', function () {
    $specialist = mkSpecialist();
    $payment    = Payment::factory()->recorded()->create();

    Livewire::actingAs($specialist)
        ->test(PaymentDetail::class, ['payment' => $payment])
        ->set('confirmEventId', 'evt-test-' . Str::random(8))
        ->call('confirm')
        ->assertDispatched('notify');

    expect($payment->fresh()->status)->toBe(PaymentStatus::CONFIRMED);
});

it('voids a payment via PaymentDetail', function () {
    $specialist = mkSpecialist();
    $payment    = Payment::factory()->recorded()->create();

    Livewire::actingAs($specialist)
        ->test(PaymentDetail::class, ['payment' => $payment])
        ->call('void')
        ->assertDispatched('notify');

    expect($payment->fresh()->status)->toBe(PaymentStatus::VOIDED);
});

// ── PaymentIndex ──────────────────────────────────────────────────────────────

it('lists payments in PaymentIndex', function () {
    $specialist = mkSpecialist();
    $payment    = Payment::factory()->confirmed()->create();

    Livewire::actingAs($specialist)
        ->test(PaymentIndex::class)
        ->assertSee(substr($payment->id, 0, 8));
});

it('filters payments by status in PaymentIndex', function () {
    $specialist    = mkSpecialist();
    $recordedUser  = User::factory()->create(['email' => 'recorded-user@test.com']);
    $confirmedUser = User::factory()->create(['email' => 'confirmed-user@test.com']);
    Payment::factory()->recorded()->for($recordedUser)->create();
    Payment::factory()->confirmed()->for($confirmedUser)->create();

    Livewire::actingAs($specialist)
        ->test(PaymentIndex::class)
        ->set('filterStatus', PaymentStatus::RECORDED->value)
        ->assertSee('recorded-user@test.com')
        ->assertDontSee('confirmed-user@test.com');
});

// ── SettlementIndex ────────────────────────────────────────────────────────────

it('lists settlements in SettlementIndex', function () {
    $specialist = mkSpecialist();
    Settlement::factory()->reconciled()->create(['settlement_date' => '2025-01-15']);

    Livewire::actingAs($specialist)
        ->test(SettlementIndex::class)
        ->assertSee('Jan 15, 2025');
});

// ── SettlementDetail ───────────────────────────────────────────────────────────

it('resolves a settlement exception via SettlementDetail', function () {
    $specialist = mkSpecialist();
    $settlement = Settlement::factory()->withException()->forToday()->create();
    $exception  = SettlementException::factory()->create([
        'settlement_id' => $settlement->id,
        'status'        => ExceptionStatus::OPEN->value,
    ]);

    Livewire::actingAs($specialist)
        ->test(SettlementDetail::class, ['settlement' => $settlement])
        ->set('resolveExceptionId', $exception->id)
        ->set('resolutionType', 'RESOLVED')
        ->set('resolutionNote', 'Confirmed with bank records.')
        ->call('resolveException')
        ->assertDispatched('notify');

    expect($exception->fresh()->status)->toBe(ExceptionStatus::RESOLVED);
});

it('validates resolution note minimum length', function () {
    $specialist = mkSpecialist();
    $settlement = Settlement::factory()->withException()->forToday()->create();
    $exception  = SettlementException::factory()->create([
        'settlement_id' => $settlement->id,
        'status'        => ExceptionStatus::OPEN->value,
    ]);

    Livewire::actingAs($specialist)
        ->test(SettlementDetail::class, ['settlement' => $settlement])
        ->set('resolveExceptionId', $exception->id)
        ->set('resolutionType', 'RESOLVED')
        ->set('resolutionNote', 'Sml')
        ->call('resolveException')
        ->assertHasErrors(['resolutionNote']);
});

it('rejects resolving an exception that belongs to a different settlement', function () {
    // Guard against tampered component state: an attacker loads SettlementDetail
    // for settlement A and sets resolveExceptionId to an exception belonging to
    // settlement B, hoping to resolve it out of context.
    $specialist  = mkSpecialist();
    $settlementA = Settlement::factory()->withException()->forToday()->create();
    $settlementB = Settlement::factory()->withException()->state(['settlement_date' => now()->subDay()->toDateString()])->create();

    $exceptionForB = SettlementException::factory()->create([
        'settlement_id' => $settlementB->id,
        'status'        => ExceptionStatus::OPEN->value,
    ]);

    // Component is mounted on settlement A but resolveExceptionId points to B's exception.
    Livewire::actingAs($specialist)
        ->test(SettlementDetail::class, ['settlement' => $settlementA])
        ->set('resolveExceptionId', $exceptionForB->id)
        ->set('resolutionType', 'RESOLVED')
        ->set('resolutionNote', 'Attempting cross-settlement resolve.')
        ->call('resolveException')
        ->assertStatus(404);

    // The exception on settlement B must remain OPEN.
    expect($exceptionForB->fresh()->status)->toBe(ExceptionStatus::OPEN);
});

// ── InvoiceBuilder ─────────────────────────────────────────────────────────────

it('creates an invoice via InvoiceBuilder', function () {
    $specialist = mkSpecialist();
    $member     = User::factory()->create();

    Livewire::actingAs($specialist)
        ->test(InvoiceBuilder::class)
        ->set('selectedUserId', $member->id)
        ->call('createInvoice')
        ->assertDispatched('notify');

    expect(Invoice::where('user_id', $member->id)->exists())->toBeTrue();
});

it('adds a line item via InvoiceBuilder', function () {
    $specialist = mkSpecialist();
    $invoice    = Invoice::factory()->create();

    Livewire::actingAs($specialist)
        ->test(InvoiceBuilder::class, ['invoice' => $invoice])
        ->set('lineDescription', 'Membership fee')
        ->set('lineAmount', '150.00')
        ->set('lineType', LineItemType::MEMBERSHIP->value)
        ->call('addLine');

    expect($invoice->fresh()->lineItems()->count())->toBe(1);
    expect($invoice->fresh()->total_cents)->toBe(15000);
});

it('issues the invoice via InvoiceBuilder', function () {
    $specialist = mkSpecialist();
    $invoice    = Invoice::factory()->create();
    $invoice->lineItems()->create([
        'description'  => 'Test item',
        'amount_cents' => 5000,
        'line_type'    => LineItemType::MEMBERSHIP->value,
        'sort_order'   => 1,
    ]);

    Livewire::actingAs($specialist)
        ->test(InvoiceBuilder::class, ['invoice' => $invoice])
        ->call('issue')
        ->assertRedirect(route('finance.invoices.show', $invoice));

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::ISSUED);
});

// ── InvoiceDetail ──────────────────────────────────────────────────────────────

it('marks invoice as paid via InvoiceDetail', function () {
    $specialist = mkSpecialist();
    $invoice    = Invoice::factory()->issued()->create();

    Livewire::actingAs($specialist)
        ->test(InvoiceDetail::class, ['invoice' => $invoice])
        ->call('markPaid')
        ->assertDispatched('notify');

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::PAID);
});

it('voids invoice via InvoiceDetail', function () {
    $specialist = mkSpecialist();
    $invoice    = Invoice::factory()->issued()->create();

    Livewire::actingAs($specialist)
        ->test(InvoiceDetail::class, ['invoice' => $invoice])
        ->call('void')
        ->assertDispatched('notify');

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::VOIDED);
});

it('cannot void a PAID invoice', function () {
    $specialist = mkSpecialist();
    $invoice    = Invoice::factory()->paid()->create();

    Livewire::actingAs($specialist)
        ->test(InvoiceDetail::class, ['invoice' => $invoice])
        ->call('void')
        ->assertHasErrors(['void']);
});

// ── InvoiceIndex ──────────────────────────────────────────────────────────────

it('lists invoices in InvoiceIndex', function () {
    $specialist = mkSpecialist();
    $invoice    = Invoice::factory()->issued()->create();

    Livewire::actingAs($specialist)
        ->test(InvoiceIndex::class)
        ->assertSee($invoice->invoice_number);
});

it('filters invoices by status in InvoiceIndex', function () {
    $specialist = mkSpecialist();
    $draft      = Invoice::factory()->create();
    $paid       = Invoice::factory()->paid()->create();

    $component = Livewire::actingAs($specialist)
        ->test(InvoiceIndex::class)
        ->set('filterStatus', InvoiceStatus::DRAFT->value);

    $component->assertSee($draft->invoice_number)
              ->assertDontSee($paid->invoice_number);
});

// ── StatementExport ────────────────────────────────────────────────────────────

it('validates settlement selection in StatementExport', function () {
    Livewire::actingAs(mkSpecialist())
        ->test(StatementExport::class)
        ->call('download')
        ->assertHasErrors(['settlementId']);
});
