<?php

namespace App\Http\Controllers\Api;

use App\Enums\TenderType;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PaymentApiController extends Controller
{
    /**
     * POST /api/payments
     *
     * Record a new payment on behalf of a user (Finance Specialist / Admin).
     *
     * Body:
     *   user_id          uuid     required
     *   tender_type      string   required  (CASH|CHECK|CARD_ON_FILE|WIRE)
     *   amount_cents     int      required  min:1
     *   reference_number string   optional
     *   idempotency_key  string   required  (also accepted as Idempotency-Key header)
     *
     * 201 Created – Payment JSON
     * 422         – Validation / business rule failure
     */
    public function record(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id'          => ['required', 'uuid', 'exists:users,id'],
            'tender_type'      => ['required', 'in:' . implode(',', array_column(TenderType::cases(), 'value'))],
            'amount_cents'     => ['required', 'integer', 'min:1'],
            'reference_number' => ['nullable', 'string', 'max:128'],
            // Required — the same amount/tender/user tuple can represent two
            // distinct legitimate payments; there is no stable field combination
            // from which a deterministic key can be derived.  Callers must
            // supply a client-generated key (e.g. UUID v4) so that retries
            // collapse onto the original record instead of creating duplicates.
            'idempotency_key'  => ['required', 'string', 'max:128'],
        ]);

        $user = User::findOrFail($data['user_id']);
        $key  = $data['idempotency_key']
            ?? $request->header('Idempotency-Key');

        try {
            $payment = app(PaymentService::class)->recordPayment(
                $user,
                TenderType::from($data['tender_type']),
                $data['amount_cents'],
                $data['reference_number'] ?? null,
                $key,
            );
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }

        return response()->json($payment, 201);
    }

    /**
     * POST /api/payments/{payment}/confirm
     *
     * Confirm a RECORDED payment (Finance Specialist / Admin).
     *
     * Body:
     *   confirmation_event_id  string  required  (external terminal transaction ID)
     *   idempotency_key        string  optional  (caller-stable retry key;
     *                                             defaults to confirmation_event_id)
     *
     * Idempotency is two-layered:
     *   1. `idempotency_key` — deduplicated via IdempotencyStore so retrying the
     *      same HTTP call never double-confirms.
     *   2. `confirmation_event_id` — globally unique per payment; the service also
     *      rejects reuse of the same event ID on a different payment (409).
     *
     * 200 OK  – Payment JSON (status: CONFIRMED)
     * 409     – confirmation_event_id already used by a different payment
     * 422     – Payment not in RECORDED state
     */
    public function confirm(Request $request, Payment $payment): JsonResponse
    {
        $data = $request->validate([
            'confirmation_event_id' => ['required', 'string', 'max:128'],
            'idempotency_key'       => ['nullable', 'string', 'max:128'],
        ]);

        $idempotencyKey = $data['idempotency_key']
            ?? $request->header('Idempotency-Key')
            ?? $data['confirmation_event_id'];

        try {
            $confirmed = app(PaymentService::class)->confirmPayment(
                $payment,
                $data['confirmation_event_id'],
                $idempotencyKey,
            );
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }

        return response()->json($confirmed);
    }

    /**
     * POST /api/payments/{payment}/void
     *
     * Void a RECORDED or CONFIRMED payment (Finance Specialist / Admin).
     *
     * Body:
     *   idempotency_key  string  optional  (also accepted as Idempotency-Key header)
     *
     * 200 OK  – Payment JSON (status: VOIDED)
     * 422     – Payment already voided
     */
    public function void(Request $request, Payment $payment): JsonResponse
    {
        // Deterministic fallback: a given payment can only be voided once, so
        // the payment ID alone is a stable, collision-free key.
        $key = $request->input('idempotency_key')
            ?? $request->header('Idempotency-Key')
            ?? 'payment.void.' . $payment->id;

        try {
            $voided = app(PaymentService::class)->voidPayment($payment, $key);
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
