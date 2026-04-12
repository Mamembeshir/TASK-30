<?php

namespace App\Http\Controllers\Api;

use App\Enums\DocumentType;
use App\Enums\UserRole;
use App\Models\CredentialingCase;
use App\Models\Doctor;
use App\Models\User;
use App\Services\CredentialingService;
use App\Services\DocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
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

        // Deterministic fallback: only one active reject is possible per case,
        // so the case ID alone uniquely identifies this operation.
        $key = $data['idempotency_key']
            ?? $request->header('Idempotency-Key')
            ?? 'credentialing.reject.' . $case->id;

        try {
            app(CredentialingService::class)->reject($case, $request->user(), $data['notes'], $key);
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }

        return response()->json($case->fresh());
    }

    /**
     * POST /api/credentialing/cases/{case}/start-review
     *
     * Transition a case from SUBMITTED to INITIAL_REVIEW (assigned reviewer / Admin).
     *
     * Body:
     *   idempotency_key  string  optional
     *
     * 200 OK  – CredentialingCase JSON
     * 403     – Actor is not the assigned reviewer
     * 422     – Case not in the right state / no reviewer assigned
     */
    public function startReview(Request $request, CredentialingCase $case): JsonResponse
    {
        $key = $request->input('idempotency_key')
            ?? $request->header('Idempotency-Key')
            ?? 'credentialing.start_review.' . $case->id;

        try {
            app(CredentialingService::class)->startReview($case, $request->user(), $key);
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }

        return response()->json($case->fresh());
    }

    /**
     * POST /api/credentialing/cases/{case}/request-materials
     *
     * Request additional materials from the doctor (assigned reviewer / Admin).
     *
     * Body:
     *   notes            string  required  min:10
     *   idempotency_key  string  optional
     *
     * 200 OK  – CredentialingCase JSON
     * 403     – Actor is not the assigned reviewer
     * 422     – Wrong status / notes too short
     */
    public function requestMaterials(Request $request, CredentialingCase $case): JsonResponse
    {
        $data = $request->validate([
            'notes'           => ['required', 'string', 'min:10'],
            'idempotency_key' => ['nullable', 'string', 'max:128'],
        ]);

        $key = $data['idempotency_key']
            ?? $request->header('Idempotency-Key')
            ?? 'credentialing.request_materials.' . $case->id . '.' . substr(sha1($data['notes']), 0, 12);

        try {
            app(CredentialingService::class)->requestMaterials($case, $request->user(), $data['notes'], $key);
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }

        return response()->json($case->fresh());
    }

    /**
     * POST /api/credentialing/cases/{case}/upload-document
     *
     * Upload a document for the doctor associated with the case
     * (Admin or assigned reviewer only).
     *
     * Body (multipart/form-data):
     *   file             file    required  max:10 MB
     *   document_type    string  required  (DocumentType enum value)
     *   idempotency_key  string  optional  (or Idempotency-Key header)
     *
     * 201 Created – DoctorDocument JSON
     * 403         – Actor not authorised to upload for this doctor
     * 422         – Validation / business rule failure
     */
    public function uploadDocumentForCase(Request $request, CredentialingCase $case): JsonResponse
    {
        $data = $request->validate([
            'file'            => ['required', 'file', 'max:10240'],
            'document_type'   => ['required', 'in:' . implode(',', array_column(DocumentType::cases(), 'value'))],
            'idempotency_key' => ['nullable', 'string', 'max:128'],
        ]);

        $documentService = app(DocumentService::class);

        if (! $documentService->canUploadFor($case->doctor, $request->user())) {
            return response()->json(['message' => 'You are not authorised to upload documents for this doctor.'], 403);
        }

        $key = $data['idempotency_key']
            ?? $request->header('Idempotency-Key')
            ?? 'credentialing.document.upload.' . $case->doctor_id . '.' . $data['document_type'];

        try {
            $doc = $documentService->upload(
                $case->doctor,
                $request->file('file'),
                DocumentType::from($data['document_type']),
                $request->user(),
                $key,
            );
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }

        return response()->json($doc, 201);
    }

    /**
     * POST /api/credentialing/doctors/{doctor}/upload-document
     *
     * Upload a document for the authenticated doctor (self-upload).
     *
     * Body (multipart/form-data):
     *   file             file    required  max:10 MB
     *   document_type    string  required  (DocumentType enum value)
     *   idempotency_key  string  optional  (or Idempotency-Key header)
     *
     * 201 Created – DoctorDocument JSON
     * 403         – Caller is not the doctor
     * 422         – Validation / business rule failure
     */
    public function uploadDocumentForDoctor(Request $request, Doctor $doctor): JsonResponse
    {
        // Only the doctor themselves may call this endpoint
        if ($doctor->user_id !== $request->user()->id) {
            abort(403);
        }

        $data = $request->validate([
            'file'            => ['required', 'file', 'max:10240'],
            'document_type'   => ['required', 'in:' . implode(',', array_column(DocumentType::cases(), 'value'))],
            'idempotency_key' => ['nullable', 'string', 'max:128'],
        ]);

        $key = $data['idempotency_key']
            ?? $request->header('Idempotency-Key')
            ?? 'credentialing.document.upload.' . $doctor->id . '.' . $data['document_type'];

        try {
            $doc = app(DocumentService::class)->upload(
                $doctor,
                $request->file('file'),
                DocumentType::from($data['document_type']),
                $request->user(),
                $key,
            );
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }

        return response()->json($doc, 201);
    }

    /**
     * POST /api/credentialing/doctors/{doctor}/submit-case
     *
     * Submit a new credentialing case for the authenticated doctor.
     *
     * Body:
     *   idempotency_key  string  optional
     *
     * 201 Created – CredentialingCase JSON
     * 403         – Caller is not the doctor
     * 422         – Business rule failure
     */
    public function submitCase(Request $request, Doctor $doctor): JsonResponse
    {
        // Only the doctor themselves may call this endpoint
        if ($doctor->user_id !== $request->user()->id) {
            abort(403);
        }

        $request->validate([
            'idempotency_key' => ['nullable', 'string', 'max:128'],
        ]);

        $key = $request->input('idempotency_key')
            ?? $request->header('Idempotency-Key')
            ?? 'cred:submit:' . $doctor->id . ':' . $doctor->credentialing_status->value;

        try {
            $case = app(CredentialingService::class)->submitCase($doctor, $request->user(), $key);
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }

        return response()->json($case, 201);
    }

    /**
     * POST /api/credentialing/doctors/{doctor}/resubmit-case
     *
     * Resubmit materials for the active case (doctor only).
     * Transitions the active case from MORE_MATERIALS_REQUESTED to RE_REVIEW.
     *
     * Body:
     *   idempotency_key  string  optional
     *
     * 200 OK  – CredentialingCase JSON
     * 403     – Caller is not the doctor
     * 422     – No active case / wrong state
     */
    public function resubmitCase(Request $request, Doctor $doctor): JsonResponse
    {
        // Only the doctor themselves may call this endpoint
        if ($doctor->user_id !== $request->user()->id) {
            abort(403);
        }

        $activeCase = $doctor->activeCase();

        if (! $activeCase) {
            return response()->json(['message' => 'No active case to resubmit.'], 422);
        }

        $key = $request->input('idempotency_key')
            ?? $request->header('Idempotency-Key')
            ?? 'credentialing.receive_materials.' . $activeCase->id;

        try {
            app(CredentialingService::class)->receiveMaterials($activeCase, $request->user(), $key);
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }

        return response()->json($activeCase->fresh());
    }

    // ── Internal helpers ──────────────────────────────────────────────────────

    private function serviceError(\RuntimeException $e): JsonResponse
    {
        $code   = $e->getCode();
        $status = ($code >= 400 && $code < 600) ? $code : 422;
        return response()->json(['message' => $e->getMessage()], $status);
    }
}
