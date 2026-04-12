<?php

namespace App\Http\Controllers\Api;

use App\Enums\ReviewStatus;
use App\Models\Trip;
use App\Models\TripReview;
use App\Services\AuditService;
use App\Services\IdempotencyStore;
use App\Services\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ReviewApiController extends Controller
{
    /**
     * POST /api/trips/{trip}/reviews
     *
     * Submit a new review for a trip the user has attended.
     *
     * Body:
     *   rating           int     required  min:1 max:5
     *   review_text      string  optional  max:2000
     *   idempotency_key  string  optional
     *
     * 201 Created – TripReview JSON
     * 422         – Not eligible / already reviewed / validation failure
     */
    public function create(Request $request, Trip $trip): JsonResponse
    {
        $data = $request->validate([
            'rating'          => ['required', 'integer', 'min:1', 'max:5'],
            'review_text'     => ['nullable', 'string', 'max:2000'],
            'idempotency_key' => ['nullable', 'string', 'max:128'],
        ]);

        $key = $data['idempotency_key']
            ?? $request->header('Idempotency-Key')
            ?? 'review:' . $trip->id . ':' . $request->user()->id;

        try {
            $review = app(ReviewService::class)->create(
                $trip,
                $request->user(),
                $data['rating'],
                $data['review_text'] ?? null,
                $key,
            );
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }

        return response()->json($review, 201);
    }

    /**
     * PUT /api/reviews/{review}
     *
     * Update the authenticated user's own review.
     *
     * Body:
     *   rating        int     required  min:1 max:5
     *   review_text   string  optional  max:2000
     *
     * 200 OK  – TripReview JSON
     * 403     – Not the author
     * 422     – Review not ACTIVE / validation failure
     */
    public function update(Request $request, TripReview $review): JsonResponse
    {
        if ($review->user_id !== $request->user()->id) {
            abort(403);
        }

        $data = $request->validate([
            'rating'          => ['required', 'integer', 'min:1', 'max:5'],
            'review_text'     => ['nullable', 'string', 'max:2000'],
            'idempotency_key' => ['nullable', 'string', 'max:128'],
        ]);

        $key = $data['idempotency_key']
            ?? $request->header('Idempotency-Key')
            ?? 'review.update.' . $review->id . '.' . md5(($data['rating'] ?? '') . '|' . ($data['review_text'] ?? ''));

        try {
            $updated = app(ReviewService::class)->update(
                $review,
                $request->user(),
                $data['rating'],
                $data['review_text'] ?? null,
                $key,
            );
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }

        return response()->json($updated);
    }

    /**
     * POST /api/reviews/{review}/flag
     *
     * Flag a review (Admin only).
     *
     * Body:
     *   idempotency_key  string  optional
     *
     * 200 OK  – TripReview JSON (status: FLAGGED)
     * 422     – Review not ACTIVE
     */
    public function flag(Request $request, TripReview $review): JsonResponse
    {
        $key = $request->input('idempotency_key')
            ?? $request->header('Idempotency-Key')
            ?? 'review.flag.' . $review->id;

        try {
            $flagged = app(ReviewService::class)->flag($review, $key);
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }

        return response()->json($flagged);
    }

    /**
     * POST /api/reviews/{review}/remove
     *
     * Remove a review (Admin only).
     *
     * Body:
     *   idempotency_key  string  optional
     *
     * 200 OK  – TripReview JSON (status: REMOVED)
     * 422     – Review already removed
     */
    public function remove(Request $request, TripReview $review): JsonResponse
    {
        $key = $request->input('idempotency_key')
            ?? $request->header('Idempotency-Key')
            ?? 'review.remove.' . $review->id;

        try {
            $removed = app(ReviewService::class)->remove($review, $key);
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }

        return response()->json($removed);
    }

    /**
     * POST /api/reviews/{review}/restore
     *
     * Restore a FLAGGED review to ACTIVE (Admin only).
     *
     * 200 OK  – TripReview JSON (status: ACTIVE)
     * 422     – Review not in FLAGGED status
     */
    public function restore(Request $request, TripReview $review): JsonResponse
    {
        if ($review->status !== ReviewStatus::FLAGGED) {
            return response()->json(['message' => 'Only flagged reviews can be restored.'], 422);
        }

        $key   = $request->input('idempotency_key')
            ?? $request->header('Idempotency-Key')
            ?? 'review.restore.' . $review->id;
        $store = new IdempotencyStore();

        if ($store->alreadyProcessed($key, 'review.restore', $review->id)) {
            return response()->json($review->fresh());
        }

        $before         = ['status' => $review->status->value];
        $review->status = ReviewStatus::ACTIVE;
        $review->saveWithLock();

        app(ReviewService::class)->recomputeAverageRating($review->trip);

        AuditService::record('review.restored', 'TripReview', $review->id, $before, [
            'status' => ReviewStatus::ACTIVE->value,
        ]);

        $store->record($key, 'review.restore', 'TripReview', $review->id);

        return response()->json($review->fresh());
    }

    // ── Internal helpers ──────────────────────────────────────────────────────

    private function serviceError(\RuntimeException $e): JsonResponse
    {
        $code   = $e->getCode();
        $status = ($code >= 400 && $code < 600) ? $code : 422;
        return response()->json(['message' => $e->getMessage()], $status);
    }
}
