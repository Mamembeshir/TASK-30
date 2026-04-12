<?php

namespace App\Http\Controllers\Api;

use App\Enums\RefundType;
use App\Models\MembershipOrder;
use App\Models\MembershipPlan;
use App\Models\Refund;
use App\Services\MembershipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class MembershipApiController extends Controller
{
    /**
     * POST /api/membership/plans/{plan}/purchase
     *
     * Create a PENDING membership order for the authenticated user.
     *
     * Body:
     *   idempotency_key  string  required
     *
     * 201 Created – MembershipOrder JSON
     * 422         – Already has active membership / plan inactive / validation failure
     */
    public function purchase(Request $request, MembershipPlan $plan): JsonResponse
    {
        $data = $request->validate([
            'idempotency_key' => ['required', 'string', 'max:128'],
        ]);

        try {
            $order = app(MembershipService::class)->purchase(
                $request->user(),
                $plan,
                $data['idempotency_key'],
            );
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }

        return response()->json($order, 201);
    }

    /**
     * POST /api/membership/plans/{plan}/top-up
     *
     * Create a TOP_UP membership order (upgrade within 30 days).
     *
     * Body:
     *   idempotency_key  string  required
     *
     * 201 Created – MembershipOrder JSON
     * 422         – No active membership / upgrade window expired / plan inactive
     */
    public function topUp(Request $request, MembershipPlan $plan): JsonResponse
    {
        $data = $request->validate([
            'idempotency_key' => ['required', 'string', 'max:128'],
        ]);

        try {
            $order = app(MembershipService::class)->topUp(
                $request->user(),
                $plan,
                $data['idempotency_key'],
            );
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }

        return response()->json($order, 201);
    }

    /**
     * POST /api/membership/orders/{order}/refund
     *
     * Request a refund for the caller's own PAID order.
     *
     * Body:
     *   refund_type    string  required  (FULL|PARTIAL)
     *   reason         string  required  min:10
     *   amount_cents   int     required when refund_type=PARTIAL
     *   idempotency_key string optional
     *
     * 201 Created – Refund JSON (status: PENDING)
     * 403         – Caller does not own the order
     * 422         – Order not PAID / invalid amount / validation failure
     */
    public function requestRefund(Request $request, MembershipOrder $order): JsonResponse
    {
        // Ownership check
        if ((string) $order->user_id !== (string) $request->user()->id) {
            abort(403);
        }

        $data = $request->validate([
            'refund_type'     => ['required', 'in:FULL,PARTIAL'],
            'reason'          => ['required', 'string', 'min:10'],
            'amount_cents'    => ['required_if:refund_type,PARTIAL', 'nullable', 'integer', 'min:1'],
            'idempotency_key' => ['nullable', 'string', 'max:128'],
        ]);

        $key = $data['idempotency_key']
            ?? $request->header('Idempotency-Key')
            ?? 'refund-order-' . $order->id . '-' . $request->user()->id;

        $type        = RefundType::from($data['refund_type']);
        $amountCents = $data['refund_type'] === 'PARTIAL'
            ? (int) $data['amount_cents']
            : null;

        try {
            $refund = app(MembershipService::class)->requestRefund(
                $order,
                $type,
                $data['reason'],
                $amountCents,
                $key,
                (string) $request->user()->id,
            );
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }

        return response()->json($refund, 201);
    }

    /**
     * POST /api/membership/refunds/{refund}/approve
     *
     * Approve a PENDING refund (Finance Specialist / Admin).
     *
     * Body:
     *   idempotency_key  string  optional
     *
     * 200 OK  – Refund JSON (status: APPROVED)
     * 422     – Refund not in PENDING state
     */
    public function approveRefund(Request $request, Refund $refund): JsonResponse
    {
        $key = $request->input('idempotency_key')
            ?? $request->header('Idempotency-Key')
            ?? 'refund.approve.' . $refund->id;

        try {
            $approved = app(MembershipService::class)->approveRefund($refund, $request->user(), $key);
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }

        return response()->json($approved);
    }

    /**
     * POST /api/membership/refunds/{refund}/process
     *
     * Process an APPROVED refund (Finance Specialist / Admin).
     *
     * Body:
     *   idempotency_key  string  optional
     *
     * 200 OK  – Refund JSON (status: PROCESSED)
     * 422     – Refund not in APPROVED state
     */
    public function processRefund(Request $request, Refund $refund): JsonResponse
    {
        $key = $request->input('idempotency_key')
            ?? $request->header('Idempotency-Key')
            ?? 'refund.process.' . $refund->id;

        try {
            $processed = app(MembershipService::class)->processRefund($refund, $key);
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }

        return response()->json($processed);
    }

    // ── Internal helpers ──────────────────────────────────────────────────────

    private function serviceError(\RuntimeException $e): JsonResponse
    {
        $code   = $e->getCode();
        $status = ($code >= 400 && $code < 600) ? $code : 422;
        return response()->json(['message' => $e->getMessage()], $status);
    }
}
