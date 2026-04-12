<?php

namespace App\Http\Controllers\Api;

use App\Enums\TripDifficulty;
use App\Models\Trip;
use App\Services\TripService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

class TripManageApiController extends Controller
{
    /**
     * POST /api/admin/trips
     *
     * Create a new DRAFT trip (Admin only).
     *
     * Body:
     *   title            string  required  max:300
     *   description      string  optional
     *   lead_doctor_id   uuid    required  exists:doctors
     *   specialty        string  required  max:200
     *   destination      string  required  max:300
     *   start_date       date    required  after_or_equal:today
     *   end_date         date    required  after_or_equal:start_date
     *   difficulty_level string  required  (TripDifficulty enum)
     *   prerequisites    string  optional
     *   total_seats      int     required  min:1 max:500
     *   price_cents      int     required  min:0
     *   idempotency_key  string  optional
     *
     * 201 Created – Trip JSON
     * 422         – Validation / business rule failure
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'           => 'required|string|max:300',
            'description'     => 'nullable|string',
            'lead_doctor_id'  => 'required|uuid|exists:doctors,id',
            'specialty'       => 'required|string|max:200',
            'destination'     => 'required|string|max:300',
            'start_date'      => 'required|date|after_or_equal:today',
            'end_date'        => 'required|date|after_or_equal:start_date',
            'difficulty_level' => 'required|in:' . implode(',', array_column(TripDifficulty::cases(), 'value')),
            'prerequisites'   => 'nullable|string',
            'total_seats'     => 'required|integer|min:1|max:500',
            'price_cents'     => 'required|integer|min:0',
            'idempotency_key' => 'nullable|string|max:128',
        ]);

        $key = $data['idempotency_key']
            ?? $request->header('Idempotency-Key')
            ?? (string) Str::uuid();

        $payload = [
            'title'            => $data['title'],
            'description'      => $data['description'] ?? null,
            'lead_doctor_id'   => $data['lead_doctor_id'],
            'specialty'        => $data['specialty'],
            'destination'      => $data['destination'],
            'start_date'       => $data['start_date'],
            'end_date'         => $data['end_date'],
            'difficulty_level' => $data['difficulty_level'],
            'prerequisites'    => $data['prerequisites'] ?? null,
            'total_seats'      => $data['total_seats'],
            'price_cents'      => $data['price_cents'],
        ];

        try {
            $trip = app(TripService::class)->create($payload, $request->user(), $key);
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }

        return response()->json($trip, 201);
    }

    /**
     * PUT /api/admin/trips/{trip}
     *
     * Update a DRAFT trip's metadata (Admin only).
     *
     * Body: same fields as store, all optional, only provided fields updated.
     *
     * 200 OK  – Trip JSON
     * 422     – Trip not in DRAFT / validation failure
     */
    public function update(Request $request, Trip $trip): JsonResponse
    {
        $data = $request->validate([
            'title'           => 'nullable|string|max:300',
            'description'     => 'nullable|string',
            'lead_doctor_id'  => 'nullable|uuid|exists:doctors,id',
            'specialty'       => 'nullable|string|max:200',
            'destination'     => 'nullable|string|max:300',
            'start_date'      => 'nullable|date|after_or_equal:today',
            'end_date'        => 'nullable|date|after_or_equal:start_date',
            'difficulty_level' => 'nullable|in:' . implode(',', array_column(TripDifficulty::cases(), 'value')),
            'prerequisites'   => 'nullable|string',
            'total_seats'     => 'nullable|integer|min:1|max:500',
            'price_cents'     => 'nullable|integer|min:0',
            'idempotency_key' => 'nullable|string|max:128',
        ]);

        // Map camelCase→snake_case fields and strip nulls
        $payload = array_filter([
            'title'            => $data['title'] ?? null,
            'description'      => array_key_exists('description', $data) ? ($data['description'] ?? null) : null,
            'lead_doctor_id'   => $data['lead_doctor_id'] ?? null,
            'specialty'        => $data['specialty'] ?? null,
            'destination'      => $data['destination'] ?? null,
            'start_date'       => $data['start_date'] ?? null,
            'end_date'         => $data['end_date'] ?? null,
            'difficulty_level' => $data['difficulty_level'] ?? null,
            'prerequisites'    => array_key_exists('prerequisites', $data) ? ($data['prerequisites'] ?? null) : null,
            'total_seats'      => $data['total_seats'] ?? null,
            'price_cents'      => $data['price_cents'] ?? null,
        ], fn ($v) => $v !== null);

        $key = $data['idempotency_key']
            ?? $request->header('Idempotency-Key')
            ?? 'trip.update.' . $trip->id . '.' . md5(json_encode($payload));

        try {
            $updated = app(TripService::class)->update($trip, $payload, $key);
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }

        return response()->json($updated);
    }

    /**
     * POST /api/admin/trips/{trip}/publish
     *
     * Publish a DRAFT trip (Admin only).
     *
     * Body:
     *   idempotency_key  string  optional
     *
     * 200 OK  – Trip JSON (status: PUBLISHED)
     * 422     – Trip not in DRAFT / lead doctor not credentialed
     */
    public function publish(Request $request, Trip $trip): JsonResponse
    {
        $key = $request->input('idempotency_key')
            ?? $request->header('Idempotency-Key')
            ?? 'trip.publish.' . $trip->id;

        try {
            $published = app(TripService::class)->publish($trip, $key);
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }

        return response()->json($published);
    }

    /**
     * POST /api/admin/trips/{trip}/close
     *
     * Close a trip for new signups (Admin only).
     *
     * Body:
     *   idempotency_key  string  optional
     *
     * 200 OK  – Trip JSON (status: CLOSED)
     * 422     – Trip not in PUBLISHED / FULL state
     */
    public function close(Request $request, Trip $trip): JsonResponse
    {
        $key = $request->input('idempotency_key')
            ?? $request->header('Idempotency-Key')
            ?? 'trip.close.' . $trip->id;

        try {
            $closed = app(TripService::class)->close($trip, $key);
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }

        return response()->json($closed);
    }

    /**
     * POST /api/admin/trips/{trip}/cancel
     *
     * Cancel a trip and release all signups (Admin only).
     *
     * Body:
     *   idempotency_key  string  optional
     *
     * 200 OK  – Trip JSON (status: CANCELLED)
     * 422     – Trip already cancelled
     */
    public function cancel(Request $request, Trip $trip): JsonResponse
    {
        $key = $request->input('idempotency_key')
            ?? $request->header('Idempotency-Key')
            ?? 'trip.cancel.' . $trip->id;

        try {
            $cancelled = app(TripService::class)->cancel($trip, $request->user(), $key);
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }

        return response()->json($cancelled);
    }

    // ── Internal helpers ──────────────────────────────────────────────────────

    private function serviceError(\RuntimeException $e): JsonResponse
    {
        $code   = $e->getCode();
        $status = ($code >= 400 && $code < 600) ? $code : 422;
        return response()->json(['message' => $e->getMessage()], $status);
    }
}
