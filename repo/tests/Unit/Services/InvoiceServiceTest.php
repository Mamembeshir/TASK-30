<?php

use App\Enums\InvoiceStatus;
use App\Enums\LineItemType;
use App\Models\Invoice;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

// ── FIN-11: Create invoice ─────────────────────────────────────────────────────

it('creates a DRAFT invoice with sequential number', function () {
    $user    = User::factory()->create();
    $invoice = app(InvoiceService::class)->createInvoice($user, (string) Str::uuid());

    expect($invoice->status)->toBe(InvoiceStatus::DRAFT)
        ->and($invoice->user_id)->toBe($user->id)
        ->and($invoice->invoice_number)->toMatch('/^MV-\d{4}-\d{5}$/');
});

it('generates sequential invoice numbers', function () {
    $user = User::factory()->create();
    $svc  = app(InvoiceService::class);

    $a = $svc->createInvoice($user, (string) Str::uuid());
    $b = $svc->createInvoice($user, (string) Str::uuid());

    $seqA = (int) substr($a->invoice_number, -5);
    $seqB = (int) substr($b->invoice_number, -5);

    expect($seqB)->toBe($seqA + 1);
});

// ── Idempotency (Audit Issue 3) ───────────────────────────────────────────────

it('createInvoice is idempotent on idempotency_key', function () {
    $user = User::factory()->create();
    $key  = (string) Str::uuid();
    $svc  = app(InvoiceService::class);

    $first  = $svc->createInvoice($user, $key, 'First notes');
    $second = $svc->createInvoice($user, $key, 'Second notes');

    expect($first->id)->toBe($second->id)
        // Original notes preserved — retry is an alias of the first call,
        // not an overwrite. Prevents the MV-YYYY-NNNNN sequence from burning
        // a second number on retry.
        ->and($second->notes)->toBe('First notes')
        ->and(Invoice::count())->toBe(1);
});

// ── FIN-12: Add line items ─────────────────────────────────────────────────────

it('adds a line item to a DRAFT invoice', function () {
    $invoice = Invoice::factory()->create();
    $svc     = app(InvoiceService::class);

    $item = $svc->addLineItem($invoice, 'Trip deposit', 15000, LineItemType::TRIP_SIGNUP);

    expect($item->description)->toBe('Trip deposit')
        ->and($item->amount_cents)->toBe(15000)
        ->and($item->line_type)->toBe(LineItemType::TRIP_SIGNUP);
    expect($invoice->fresh()->total_cents)->toBe(15000);
});

it('recomputes total correctly after multiple line items', function () {
    $invoice = Invoice::factory()->create();
    $svc     = app(InvoiceService::class);

    $svc->addLineItem($invoice, 'First',  5000, LineItemType::MEMBERSHIP);
    $svc->addLineItem($invoice, 'Second', 3000, LineItemType::ADJUSTMENT);

    expect($invoice->fresh()->total_cents)->toBe(8000);
});

it('rejects adding a line item to a non-DRAFT invoice', function () {
    $invoice = Invoice::factory()->issued()->create();

    expect(fn () => app(InvoiceService::class)->addLineItem($invoice, 'Extra', 1000, LineItemType::ADJUSTMENT))
        ->toThrow(RuntimeException::class, 'DRAFT');
});

// ── FIN-13: Issue invoice ──────────────────────────────────────────────────────

it('issues a DRAFT invoice', function () {
    $invoice = Invoice::factory()->create();

    $issued = app(InvoiceService::class)->issueInvoice($invoice, (string) Str::uuid());

    expect($issued->status)->toBe(InvoiceStatus::ISSUED)
        ->and($issued->issued_at)->not->toBeNull();
});

it('rejects issuing a non-DRAFT invoice', function () {
    $invoice = Invoice::factory()->issued()->create();

    expect(fn () => app(InvoiceService::class)->issueInvoice($invoice, (string) Str::uuid()))
        ->toThrow(RuntimeException::class);
});

it('issueInvoice is idempotent on the same key', function () {
    $invoice = Invoice::factory()->create();
    $key     = (string) Str::uuid();
    $svc     = app(InvoiceService::class);

    $first  = $svc->issueInvoice($invoice, $key);
    $second = $svc->issueInvoice($invoice->fresh(), $key);

    expect($first->id)->toBe($second->id)
        ->and($second->status)->toBe(InvoiceStatus::ISSUED);
});

// ── FIN-14: Mark paid ──────────────────────────────────────────────────────────

it('marks an ISSUED invoice as PAID', function () {
    $invoice = Invoice::factory()->issued()->create();

    $paid = app(InvoiceService::class)->markPaid($invoice, (string) Str::uuid());

    expect($paid->status)->toBe(InvoiceStatus::PAID);
});

it('rejects marking a non-ISSUED invoice as paid', function () {
    $invoice = Invoice::factory()->create(); // DRAFT

    expect(fn () => app(InvoiceService::class)->markPaid($invoice, (string) Str::uuid()))
        ->toThrow(RuntimeException::class);
});

// ── FIN-15: Void invoice ───────────────────────────────────────────────────────

it('voids a DRAFT invoice', function () {
    $invoice = Invoice::factory()->create();

    $voided = app(InvoiceService::class)->voidInvoice($invoice, (string) Str::uuid());

    expect($voided->status)->toBe(InvoiceStatus::VOIDED);
});

it('voids an ISSUED invoice', function () {
    $invoice = Invoice::factory()->issued()->create();

    $voided = app(InvoiceService::class)->voidInvoice($invoice, (string) Str::uuid());

    expect($voided->status)->toBe(InvoiceStatus::VOIDED);
});

it('rejects voiding a PAID invoice (FIN-10)', function () {
    $invoice = Invoice::factory()->paid()->create();

    expect(fn () => app(InvoiceService::class)->voidInvoice($invoice, (string) Str::uuid()))
        ->toThrow(RuntimeException::class, 'PAID');
});

it('voidInvoice is idempotent on the same key', function () {
    $invoice = Invoice::factory()->issued()->create();
    $key     = (string) Str::uuid();
    $svc     = app(InvoiceService::class);

    $first  = $svc->voidInvoice($invoice, $key);
    $second = $svc->voidInvoice($invoice->fresh(), $key);

    expect($first->id)->toBe($second->id)
        ->and($second->status)->toBe(InvoiceStatus::VOIDED);
});
