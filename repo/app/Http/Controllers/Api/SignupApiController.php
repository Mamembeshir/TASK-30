<?php

namespace App\Http\Controllers\Api;

use App\Enums\SignupStatus;
use App\Enums\TenderType;
use App\Models\TripSignup;
use App\Services\PaymentService;
use App\Services\SeatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SignupApiController extends Controller
{
    /**
     * POST /api/signups/{signup}/payment
     *
     * Record a payment for the caller's own HOLD signup and atomically confirm
     * the seat.  The caller must own the signup; amount is derived from the
     * trip price, so no amount field is accepted (prevents manipulation).
     *
     * Body:
     *   tender_type      string  required  (CASH|CHECK|CARD_ON_FILE|WIRE)
     *   reference_number string  optional
     *   idempotency_key  string  optional  (also accepted as Idempotency-Key header)
     *
     * 200 OK  – { signup: TripSignup JSON, payment: Payment JSON }
     * 403     – Caller does not own the signup
     * 422     – Hold expired / signup not in HOLD status / validation failure
     */
    public function submitPayment(Request $request, TripSignup $signup): JsonResponse
    {
        if ($signup->user_id !== $request->user()->id) {
            abort(403);
        }

        if ($signup->status !== SignupStatus::HOLD) {
            return response()->json(['message' => 'Signup is not in HOLD status.'], 422);
        }

        if ($signup->isHoldExpired()) {
            return response()->json(['message' => 'Your hold has expired. Please restart the booking.'], 422);
        }

        $data = $request->validate([
            'tender_type'      => ['required', 'in:' . implode(',', array_column(TenderType::cases(), 'value'))],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'idempotency_key'  => ['nullable', 'string', 'max:128'],
        ]);

        $idempotencyKey = $data['idempotency_key']
            ?? $request->header('Idempotency-Key')
            ?? 'pay-signup-' . $signup->id;

        try {
            $payment = app(PaymentService::class)->recordPayment(
                $request->user(),
                TenderType::from($data['tender_type']),
                (int) $signup->trip->price_cents,
                $data['reference_number'] ?? null,
                $idempotencyKey,
            );

            $confirmed = app(SeatService::class)->confirmSeat(
                $signup,
                $payment->id,
                'seat.confirm.' . $signup->id,
            );
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }

        return response()->json(['signup' => $confirmed, 'payment' => $payment]);
    }

    private function serviceError(\RuntimeException $e): JsonResponse
    {
        $code   = $e->getCode();
        $status = ($code >= 400 && $code < 600) ? $code : 422;
        return response()->json(['message' => $e->getMessage()], $status);
    }
}
