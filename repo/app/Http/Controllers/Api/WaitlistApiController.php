<?php

namespace App\Http\Controllers\Api;

use App\Enums\HoldReleaseReason;
use App\Enums\SignupStatus;
use App\Models\TripSignup;
use App\Models\TripWaitlistEntry;
use App\Services\SeatService;
use App\Services\WaitlistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

class WaitlistApiController extends Controller
{
    /**
     * POST /api/waitlist/{entry}/accept
     *
     * Accept a waitlist offer — converts the OFFERED entry into a seat hold.
     *
     * Body:
     *   idempotency_key  string  optional  (also accepted as Idempotency-Key header)
     *
     * 201 Created – TripSignup JSON (status: HOLD)
     * 422         – No active offer / offer expired
     */
    public function acceptOffer(Request $request, TripWaitlistEntry $entry): JsonResponse
    {
        $request->validate([
            'idempotency_key' => ['nullable', 'string', 'max:128'],
        ]);

        // Ownership check — only the entry owner may accept
        if ($entry->user_id !== $request->user()->id) {
            abort(403);
        }

        $key = $request->input('idempotency_key')
            ?? $request->header('Idempotency-Key')
            ?? 'waitlist-accept-' . $entry->id;

        try {
            $signup = app(WaitlistService::class)->acceptOffer($entry, $key);
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }

        return response()->json($signup, 201);
    }

    /**
     * POST /api/waitlist/{entry}/decline
     *
     * Decline a waitlist offer.
     *
     * Body:
     *   idempotency_key  string  optional  (also accepted as Idempotency-Key header)
     *
     * 200 OK  – TripWaitlistEntry JSON (status: DECLINED)
     * 422     – No active offer to decline
     */
    public function declineOffer(Request $request, TripWaitlistEntry $entry): JsonResponse
    {
        $request->validate([
            'idempotency_key' => ['nullable', 'string', 'max:128'],
        ]);

        // Ownership check — only the entry owner may decline
        if ($entry->user_id !== $request->user()->id) {
            abort(403);
        }

        $key = $request->input('idempotency_key')
            ?? $request->header('Idempotency-Key')
            ?? 'waitlist.decline.' . $entry->id;

        try {
            app(WaitlistService::class)->declineOffer($entry, $key);
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }

        return response()->json($entry->fresh());
    }

    /**
     * POST /api/signups/{signup}/cancel
     *
     * Cancel a HOLD or CONFIRMED signup owned by the authenticated user.
     *
     * 200 OK  – TripSignup JSON (status: CANCELLED or EXPIRED)
     * 403     – Caller does not own the signup
     * 422     – Signup is not in a cancellable state
     */
    public function cancelSignup(Request $request, TripSignup $signup): JsonResponse
    {
        // Ownership check
        if ($signup->user_id !== $request->user()->id) {
            abort(403);
        }

        $key = $request->input('idempotency_key')
            ?? $request->header('Idempotency-Key')
            ?? 'signup.cancel.' . $signup->id;

        try {
            if ($signup->status === SignupStatus::CONFIRMED) {
                app(SeatService::class)->cancelConfirmedSignup($signup, $key);
            } elseif ($signup->status === SignupStatus::HOLD) {
                app(SeatService::class)->releaseSeat($signup, HoldReleaseReason::CANCELLED, $key);
            } else {
                return response()->json(['message' => 'Signup is not in a cancellable state.'], 422);
            }
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }

        return response()->json($signup->fresh());
    }

    // ── Internal helpers ──────────────────────────────────────────────────────

    private function serviceError(\RuntimeException $e): JsonResponse
    {
        $code   = $e->getCode();
        $status = ($code >= 400 && $code < 600) ? $code : 422;
        return response()->json(['message' => $e->getMessage()], $status);
    }
}
