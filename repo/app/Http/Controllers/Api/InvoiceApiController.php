<?php

namespace App\Http\Controllers\Api;

use App\Enums\LineItemType;
use App\Models\Invoice;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class InvoiceApiController extends Controller
{
    /**
     * POST /api/invoices
     *
     * Create a new DRAFT invoice for a user (Finance Specialist / Admin).
     *
     * Body:
     *   user_id          uuid    required
     *   idempotency_key  string  required
     *   notes            string  optional
     *
     * 201 Created – Invoice JSON
     * 422         – Validation failure
     */
    public function create(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id'         => ['required', 'uuid', 'exists:users,id'],
            'idempotency_key' => ['required', 'string', 'max:128'],
            'notes'           => ['nullable', 'string'],
        ]);

        $user = User::findOrFail($data['user_id']);

        try {
            $invoice = app(InvoiceService::class)->createInvoice(
                $user,
                $data['idempotency_key'],
                $data['notes'] ?? null,
            );
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }

        return response()->json($invoice, 201);
    }

    /**
     * POST /api/invoices/{invoice}/lines
     *
     * Add a line item to a DRAFT invoice (Finance Specialist / Admin).
     *
     * Body:
     *   description      string  required  max:500
     *   amount_cents     int     required  min:1
     *   line_type        string  required  (LineItemType enum value)
     *
     * 201 Created – InvoiceLineItem JSON
     * 422         – Invoice not in DRAFT / validation failure
     */
    public function addLine(Request $request, Invoice $invoice): JsonResponse
    {
        $data = $request->validate([
            'description'    => ['required', 'string', 'max:500'],
            'amount_cents'   => ['required', 'integer', 'min:1'],
            'line_type'      => ['required', 'in:' . implode(',', array_column(LineItemType::cases(), 'value'))],
            'idempotency_key' => ['nullable', 'string', 'max:128'],
        ]);

        $key = $data['idempotency_key']
            ?? $request->header('Idempotency-Key')
            ?? 'invoice.line.add.' . $invoice->id . '.' . md5(($data['description'] ?? '') . '|' . ($data['amount_cents'] ?? '') . '|' . ($data['line_type'] ?? ''));

        try {
            $item = app(InvoiceService::class)->addLineItem(
                $invoice,
                $data['description'],
                $data['amount_cents'],
                LineItemType::from($data['line_type']),
                null,   // $referenceId — not applicable at this API layer
                $key,   // $idempotencyKey
            );
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }

        return response()->json($item, 201);
    }

    /**
     * POST /api/invoices/{invoice}/issue
     *
     * Transition invoice DRAFT → ISSUED (Finance Specialist / Admin).
     *
     * Body:
     *   idempotency_key  string  optional
     *
     * 200 OK  – Invoice JSON (status: ISSUED)
     * 422     – Invoice not in DRAFT state
     */
    public function issue(Request $request, Invoice $invoice): JsonResponse
    {
        $key = $request->input('idempotency_key')
            ?? $request->header('Idempotency-Key')
            ?? 'invoice.issue.' . $invoice->id;

        try {
            $issued = app(InvoiceService::class)->issueInvoice($invoice, $key);
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }

        return response()->json($issued);
    }

    /**
     * POST /api/invoices/{invoice}/mark-paid
     *
     * Transition invoice ISSUED → PAID (Finance Specialist / Admin).
     *
     * Body:
     *   idempotency_key  string  optional
     *
     * 200 OK  – Invoice JSON (status: PAID)
     * 422     – Invoice not in ISSUED state
     */
    public function markPaid(Request $request, Invoice $invoice): JsonResponse
    {
        $key = $request->input('idempotency_key')
            ?? $request->header('Idempotency-Key')
            ?? 'invoice.mark_paid.' . $invoice->id;

        try {
            $paid = app(InvoiceService::class)->markPaid($invoice, $key);
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }

        return response()->json($paid);
    }

    /**
     * POST /api/invoices/{invoice}/void
     *
     * Void a DRAFT or ISSUED invoice (Finance Specialist / Admin).
     *
     * Body:
     *   idempotency_key  string  optional
     *
     * 200 OK  – Invoice JSON (status: VOIDED)
     * 422     – Invoice is PAID or in wrong state
     */
    public function void(Request $request, Invoice $invoice): JsonResponse
    {
        $key = $request->input('idempotency_key')
            ?? $request->header('Idempotency-Key')
            ?? 'invoice.void.' . $invoice->id;

        try {
            $voided = app(InvoiceService::class)->voidInvoice($invoice, $key);
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }

        return response()->json($voided);
    }

    // ── Internal helpers ──────────────────────────────────────────────────────

    private function serviceError(\RuntimeException $e): JsonResponse
    {
        $code   = $e->getCode();
        $status = ($code >= 400 && $code < 600) ? $code : 422;
        return response()->json(['message' => $e->getMessage()], $status);
    }
}
