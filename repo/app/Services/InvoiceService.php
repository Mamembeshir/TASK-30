<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\LineItemType;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\Settlement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InvoiceService
{
    // ── Create ────────────────────────────────────────────────────────────────

    /**
     * Create a DRAFT invoice with an auto-sequential number (MV-YYYY-NNNNN).
     */
    public function createInvoice(User $user, ?string $notes = null): Invoice
    {
        return DB::transaction(function () use ($user, $notes) {
            $number = $this->nextInvoiceNumber();

            $invoice = Invoice::create([
                'user_id'        => $user->id,
                'invoice_number' => $number,
                'total_cents'    => 0,
                'status'         => InvoiceStatus::DRAFT->value,
                'notes'          => $notes,
                'version'        => 1,
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
     */
    public function addLineItem(
        Invoice $invoice,
        string $description,
        int $amountCents,
        LineItemType $type,
        ?string $referenceId = null
    ): InvoiceLineItem {
        if ($invoice->status !== InvoiceStatus::DRAFT) {
            throw new RuntimeException('Line items can only be added to DRAFT invoices.', 422);
        }

        return DB::transaction(function () use ($invoice, $description, $amountCents, $type, $referenceId) {
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

            return $item;
        });
    }

    // ── Issue ─────────────────────────────────────────────────────────────────

    /**
     * Transition invoice DRAFT → ISSUED.
     */
    public function issueInvoice(Invoice $invoice): Invoice
    {
        if ($invoice->status !== InvoiceStatus::DRAFT) {
            throw new RuntimeException(
                "Cannot issue an invoice in {$invoice->status->value} status.",
                422
            );
        }

        return DB::transaction(function () use ($invoice) {
            $before = $invoice->toArray();

            $invoice->status    = InvoiceStatus::ISSUED;
            $invoice->issued_at = now();
            $invoice->saveWithLock();

            AuditService::record('invoice.issued', 'Invoice', $invoice->id, $before, [
                'invoice_number' => $invoice->invoice_number,
            ]);

            return $invoice;
        });
    }

    // ── Mark paid ─────────────────────────────────────────────────────────────

    /**
     * Transition invoice ISSUED → PAID.
     */
    public function markPaid(Invoice $invoice): Invoice
    {
        if ($invoice->status !== InvoiceStatus::ISSUED) {
            throw new RuntimeException(
                "Cannot mark paid an invoice in {$invoice->status->value} status.",
                422
            );
        }

        return DB::transaction(function () use ($invoice) {
            $before = $invoice->toArray();

            $invoice->status = InvoiceStatus::PAID;
            $invoice->saveWithLock();

            AuditService::record('invoice.paid', 'Invoice', $invoice->id, $before, [
                'invoice_number' => $invoice->invoice_number,
            ]);

            return $invoice;
        });
    }

    // ── Void ──────────────────────────────────────────────────────────────────

    /**
     * Void a DRAFT or ISSUED invoice. PAID → 422 (FIN-10).
     */
    public function voidInvoice(Invoice $invoice): Invoice
    {
        if ($invoice->status === InvoiceStatus::PAID) {
            throw new RuntimeException('Cannot void a PAID invoice.', 422);
        }

        if (! in_array($invoice->status, [InvoiceStatus::DRAFT, InvoiceStatus::ISSUED], true)) {
            throw new RuntimeException(
                "Cannot void an invoice in {$invoice->status->value} status.",
                422
            );
        }

        return DB::transaction(function () use ($invoice) {
            $before = $invoice->toArray();

            $invoice->status = InvoiceStatus::VOIDED;
            $invoice->saveWithLock();

            AuditService::record('invoice.voided', 'Invoice', $invoice->id, $before, [
                'invoice_number' => $invoice->invoice_number,
            ]);

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
