<?php

namespace App\Http\Controllers\Api;

use App\Enums\TripStatus;
use App\Models\Trip;
use App\Services\SeatService;
use App\Services\WaitlistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class TripApiController extends Controller
{
    /**
     * GET /api/trips
     *
     * Returns a paginated list of publicly bookable trips (PUBLISHED + FULL).
     * FULL trips are included so callers can discover waitlist-eligible trips.
     */
    public function index(Request $request): JsonResponse
    {
        $trips = Trip::with('doctor.user.profile')
            ->whereIn('status', [TripStatus::PUBLISHED->value, TripStatus::FULL->value])
            ->orderBy('start_date')
            ->paginate(20);

        return response()->json($trips);
    }

    /**
     * GET /api/trips/{trip}
     *
     * Enforces the same visibility policy as the Livewire TripDetail component:
     * only PUBLISHED and FULL trips are visible to non-admin users. Returns 404
     * (not 403) to avoid confirming the existence of hidden trips.
     */
    public function show(Request $request, Trip $trip): JsonResponse
    {
        $visibleStatuses = [TripStatus::PUBLISHED, TripStatus::FULL];

        if (! in_array($trip->status, $visibleStatuses, true) && ! $request->user()?->isAdmin()) {
            abort(404);
        }

        return response()->json($trip->load('doctor.user.profile'));
    }

    /**
     * POST /api/trips/{trip}/hold
     *
     * Hold a seat for the authenticated user.
     * Idempotency-Key header (or `idempotency_key` body field) accepted;
     * if omitted a per-request UUID is generated.
     *
     * 201 Created  – TripSignup JSON
     * 422          – No seats available, trip not in bookable state, etc.
     */
    public function hold(Request $request, Trip $trip): JsonResponse
    {
        $request->validate([
            'idempotency_key' => ['nullable', 'string', 'max:128'],
        ]);

        // Deterministic fallback: a user can hold at most one seat per trip,
        // so user_id + trip_id is a stable, collision-free composite key.
        $key = $request->input('idempotency_key')
            ?? $request->header('Idempotency-Key')
            ?? 'trip.hold.' . $trip->id . '.' . $request->user()->id;

        try {
            $signup = app(SeatService::class)->holdSeat($trip, $request->user(), $key);
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }

        return response()->json($signup, 201);
    }

    /**
     * POST /api/trips/{trip}/waitlist
     *
     * Join the waitlist for a FULL trip.
     * Same idempotency semantics as /hold.
     *
     * 201 Created  – TripWaitlistEntry JSON
     * 422          – Trip not full / already on waitlist / etc.
     */
    public function joinWaitlist(Request $request, Trip $trip): JsonResponse
    {
        $request->validate([
            'idempotency_key' => ['nullable', 'string', 'max:128'],
        ]);

        // Deterministic fallback: a user can join the waitlist once per trip,
        // so user_id + trip_id is a stable, collision-free composite key.
        $key = $request->input('idempotency_key')
            ?? $request->header('Idempotency-Key')
            ?? 'trip.waitlist.' . $trip->id . '.' . $request->user()->id;

        try {
            $entry = app(WaitlistService::class)->joinWaitlist($trip, $request->user(), $key);
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }

        return response()->json($entry, 201);
    }

    // ── Internal helpers ──────────────────────────────────────────────────────

    private function serviceError(\RuntimeException $e): JsonResponse
    {
        $code = $e->getCode();
        $status = ($code >= 400 && $code < 600) ? $code : 422;
        return response()->json(['message' => $e->getMessage()], $status);
    }
}
