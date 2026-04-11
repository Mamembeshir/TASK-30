<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Models\CredentialingCase;
use App\Models\User;
use App\Services\CredentialingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CredentialingApiController extends Controller
{
    /**
     * POST /api/credentialing/cases/{case}/assign
     *
     * Assign a reviewer to a SUBMITTED case (Credentialing Reviewer / Admin).
     *
     * Body:
     *   reviewer_id      uuid    required
     *   idempotency_key  string  optional
     *
     * 200 OK  – CredentialingCase JSON
     * 422     – Case not in SUBMITTED state / reviewer not found
     */
    public function assignReviewer(Request $request, CredentialingCase $case): JsonResponse
    {
        $data = $request->validate([
            'reviewer_id'     => ['required', 'uuid', 'exists:users,id'],
            'idempotency_key' => ['nullable', 'string', 'max:128'],
        ]);

        $reviewer = User::findOrFail($data['reviewer_id']);

        if (! $reviewer->isCredentialingReviewer() && ! $reviewer->isAdmin()) {
            throw ValidationException::withMessages([
                'reviewer_id' => ['The selected user does not have the Credentialing Reviewer or Admin role.'],
            ]);
        }
        $key      = $data['idempotency_key']
            ?? $request->header('Idempotency-Key')
            ?? 'credentialing.assign_reviewer.' . $case->id . '.' . $reviewer->id;

        try {
            app(CredentialingService::class)->assignReviewer(
                $case,
                $reviewer,
                $request->user(),
                $key,
            );
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }

        return response()->json($case->fresh());
    }

    /**
     * POST /api/credentialing/cases/{case}/approve
     *
     * Approve a case in INITIAL_REVIEW or RE_REVIEW (Credentialing Reviewer / Admin).
     *
     * Body:
     *   idempotency_key  string  optional
     *
     * 200 OK  – CredentialingCase JSON (status: APPROVED)
     * 403     – Actor is not the assigned reviewer
     * 422     – Wrong status
     */
    public function approve(Request $request, CredentialingCase $case): JsonResponse
    {
        $key = $request->input('idempotency_key')
            ?? $request->header('Idempotency-Key')
            ?? 'credentialing.approve.' . $case->id;

        try {
            app(CredentialingService::class)->approve($case, $request->user(), $key);
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }

        return response()->json($case->fresh());
    }

    /**
     * POST /api/credentialing/cases/{case}/reject
     *
     * Reject a case in INITIAL_REVIEW or RE_REVIEW (Credentialing Reviewer / Admin).
     *
     * Body:
     *   notes            string  required  min:10
     *   idempotency_key  string  optional
     *
     * 200 OK  – CredentialingCase JSON (status: REJECTED)
     * 403     – Actor is not the assigned reviewer
     * 422     – Wrong status / notes too short
     */
    public function reject(Request $request, CredentialingCase $case): JsonResponse
    {
        $data = $request->validate([
            'notes'           => ['required', 'string', 'min:10'],
            'idempotency_key' => ['nullable', 'string', 'max:128'],
        ]);

        $key = $data['idempotency_key']
            ?? $request->header('Idempotency-Key')
            ?? 'credentialing.reject.' . $case->id . '.' . substr(sha1($data['notes']), 0, 12);

        try {
            app(CredentialingService::class)->reject($case, $request->user(), $data['notes'], $key);
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }

        return response()->json($case->fresh());
    }

    // ── Internal helpers ──────────────────────────────────────────────────────

    private function serviceError(\RuntimeException $e): JsonResponse
    {
        $code   = $e->getCode();
        $status = ($code >= 400 && $code < 600) ? $code : 422;
        return response()->json(['message' => $e->getMessage()], $status);
    }
}
