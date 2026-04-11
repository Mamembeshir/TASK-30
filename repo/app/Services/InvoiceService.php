<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\LineItemType;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\Settlement;
use App\Models\User;
use App\Services\IdempotencyStore;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InvoiceService
{
    // ── Create ────────────────────────────────────────────────────────────────

    /**
     * Create a DRAFT invoice with an auto-sequential number (MV-YYYY-NNNNN).
     *
     * Participates in the universal service-layer idempotency contract
     * (`docs/design.md:70-73`, audit Issue 3). Retries with the same key
     * return the existing draft rather than burning a second invoice
     * number in the MV-YYYY-NNNNN sequence — important because the numbers
     * are externally visible and gaps are harder to explain than retries.
     */
    public function createInvoice(User $user, string $idempotencyKey, ?string $notes = null): Invoice
    {
        $existing = Invoice::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($user, $notes, $idempotencyKey) {
            $number = $this->nextInvoiceNumber();

            $invoice = Invoice::create([
                'user_id'         => $user->id,
                'invoice_number'  => $number,
                'total_cents'     => 0,
                'status'          => InvoiceStatus::DRAFT->value,
                'notes'           => $notes,
                'version'         => 1,
                'idempotency_key' => $idempotencyKey,
            ]);

            AuditService::record('invoice.created', 'Invoice', $invoice->id, null, [
                'invoice_number' => $number,
                'user_id'        => $user->id,
            ]);

            return $invoice;
        });
    }

    // ── Line items ────────────────────────────────────────────────────────────

    /**
     * Add a line item to a DRAFT invoice and recompute total.
     *
     * An optional $idempotencyKey scopes the uniqueness to this specific
     * add-line-item call. Retries with the same key return without adding
     * a duplicate line.
     */
    public function addLineItem(
        Invoice $invoice,
        string $description,
        int $amountCents,
        LineItemType $type,
        ?string $referenceId = null,
        ?string $idempotencyKey = null,
    ): InvoiceLineItem {
        $store = new IdempotencyStore();
        if ($idempotencyKey && $store->alreadyProcessed($idempotencyKey, 'invoice.add_line_item', $invoice->id)) {
            // Return the most recently added line item as the cached response
            return $invoice->lineItems()->latest('sort_order')->firstOrFail();
        }

        if ($invoice->status !== InvoiceStatus::DRAFT) {
            throw new RuntimeException('Line items can only be added to DRAFT invoices.', 422);
        }

        return DB::transaction(function () use ($invoice, $description, $amountCents, $type, $referenceId, $idempotencyKey, $store) {
            $sortOrder = $invoice->lineItems()->count() + 1;

            $item = InvoiceLineItem::create([
                'invoice_id'   => $invoice->id,
                'description'  => $description,
                'amount_cents' => $amountCents,
                'line_type'    => $type->value,
                'reference_id' => $referenceId,
                'sort_order'   => $sortOrder,
            ]);

            // Recompute total (item already persisted above, sum includes it)
            $invoice->total_cents = (int) $invoice->lineItems()->sum('amount_cents');
            $invoice->saveWithLock();

            if ($idempotencyKey) {
                $store->record($idempotencyKey, 'invoice.add_line_item', 'Invoice', $invoice->id);
            }

            return $item;
        });
    }

    // ── Issue ─────────────────────────────────────────────────────────────────

    /**
     * Transition invoice DRAFT → ISSUED.
     *
     * `$idempotencyKey` is REQUIRED (FIN audit Issue 4). Pass a
     * deterministic per-invoice key (`invoice.issue.{invoiceId}`) so a
     * double-click on "Issue" collapses to a no-op instead of 422'ing on
     * the second request.
     */
    public function issueInvoice(Invoice $invoice, string $idempotencyKey): Invoice
    {
        $store = new IdempotencyStore();
        if ($store->alreadyProcessed($idempotencyKey, 'invoice.issue', $invoice->id)) {
            return $invoice->fresh();
        }

        if ($invoice->status !== InvoiceStatus::DRAFT) {
            throw new RuntimeException(
                "Cannot issue an invoice in {$invoice->status->value} status.",
                422
            );
        }

        return DB::transaction(function () use ($invoice, $idempotencyKey, $store) {
            $before = $invoice->toArray();

            $invoice->status    = InvoiceStatus::ISSUED;
            $invoice->issued_at = now();
            $invoice->saveWithLock();

            AuditService::record('invoice.issued', 'Invoice', $invoice->id, $before, [
                'invoice_number' => $invoice->invoice_number,
            ]);

            $store->record($idempotencyKey, 'invoice.issue', 'Invoice', $invoice->id);

            return $invoice;
        });
    }

    // ── Mark paid ─────────────────────────────────────────────────────────────

    /**
     * Transition invoice ISSUED → PAID.
     *
     * `$idempotencyKey` is REQUIRED (FIN audit Issue 4).
     */
    public function markPaid(Invoice $invoice, string $idempotencyKey): Invoice
    {
        $store = new IdempotencyStore();
        if ($store->alreadyProcessed($idempotencyKey, 'invoice.mark_paid', $invoice->id)) {
            return $invoice->fresh();
        }

        if ($invoice->status !== InvoiceStatus::ISSUED) {
            throw new RuntimeException(
                "Cannot mark paid an invoice in {$invoice->status->value} status.",
                422
            );
        }

        return DB::transaction(function () use ($invoice, $idempotencyKey, $store) {
            $before = $invoice->toArray();

            $invoice->status = InvoiceStatus::PAID;
            $invoice->saveWithLock();

            AuditService::record('invoice.paid', 'Invoice', $invoice->id, $before, [
                'invoice_number' => $invoice->invoice_number,
            ]);

            $store->record($idempotencyKey, 'invoice.mark_paid', 'Invoice', $invoice->id);

            return $invoice;
        });
    }

    // ── Void ──────────────────────────────────────────────────────────────────

    /**
     * Void a DRAFT or ISSUED invoice. PAID → 422 (FIN-10).
     *
     * `$idempotencyKey` is REQUIRED (FIN audit Issue 4).
     */
    public function voidInvoice(Invoice $invoice, string $idempotencyKey): Invoice
    {
        $store = new IdempotencyStore();
        if ($store->alreadyProcessed($idempotencyKey, 'invoice.void', $invoice->id)) {
            return $invoice->fresh();
        }

        if ($invoice->status === InvoiceStatus::PAID) {
            throw new RuntimeException('Cannot void a PAID invoice.', 422);
        }

        if (! in_array($invoice->status, [InvoiceStatus::DRAFT, InvoiceStatus::ISSUED], true)) {
            throw new RuntimeException(
                "Cannot void an invoice in {$invoice->status->value} status.",
                422
            );
        }

        return DB::transaction(function () use ($invoice, $idempotencyKey, $store) {
            $before = $invoice->toArray();

            $invoice->status = InvoiceStatus::VOIDED;
            $invoice->saveWithLock();

            AuditService::record('invoice.voided', 'Invoice', $invoice->id, $before, [
                'invoice_number' => $invoice->invoice_number,
            ]);

            $store->record($idempotencyKey, 'invoice.void', 'Invoice', $invoice->id);

            return $invoice;
        });
    }

    // ── Export statement ──────────────────────────────────────────────────────

    /**
     * Delegate to SettlementService for statement export.
     */
    public function exportStatement(Settlement $settlement): string
    {
        return app(SettlementService::class)->exportStatement($settlement);
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function nextInvoiceNumber(): string
    {
        $year = now()->year;
        $prefix = "MV-{$year}-";

        // Find highest sequential number for this year
        $last = Invoice::where('invoice_number', 'like', "{$prefix}%")
            ->orderByDesc('invoice_number')
            ->lockForUpdate()
            ->value('invoice_number');

        $seq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad($seq, 5, '0', STR_PAD_LEFT);
    }
}
