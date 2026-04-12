<?php

namespace App\Http\Controllers\Api;

use App\Enums\ExceptionStatus;
use App\Models\Settlement;
use App\Services\SettlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SettlementApiController extends Controller
{
    /**
     * POST /api/settlements/close
     *
     * Compute and close the daily settlement for a given date
     * (Finance Specialist / Admin).
     *
     * Body:
     *   date             string  required  (Y-m-d)
     *   idempotency_key  string  optional
     *
     * 200 OK  – Settlement JSON
     * 422     – Validation / business rule failure
     */
    public function close(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date'            => ['required', 'date_format:Y-m-d'],
            'idempotency_key' => ['nullable', 'string', 'max:128'],
        ]);

        $key = $data['idempotency_key']
            ?? $request->header('Idempotency-Key')
            ?? 'settlement.close.' . $data['date'];

        try {
            $settlement = app(SettlementService::class)->closeDailySettlement($data['date'], $key);
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }

        return response()->json($settlement);
    }

    /**
     * POST /api/settlements/{settlement}/resolve-exception
     *
     * Resolve or write-off an OPEN exception (Finance Specialist / Admin).
     *
     * Body:
     *   exception_id      uuid    required
     *   resolution_type   string  required  (RESOLVED|WRITTEN_OFF)
     *   resolution_note   string  required  min:5
     *   idempotency_key   string  optional
     *
     * 200 OK  – SettlementException JSON
     * 422     – Exception not OPEN / validation failure
     */
    public function resolveException(Request $request, Settlement $settlement): JsonResponse
    {
        $data = $request->validate([
            'exception_id'    => ['required', 'uuid'],
            'resolution_type' => ['required', 'in:RESOLVED,WRITTEN_OFF'],
            'resolution_note' => ['required', 'string', 'min:5'],
            'idempotency_key' => ['nullable', 'string', 'max:128'],
        ]);

        // Scope to the route-bound settlement so a caller cannot resolve
        // exceptions belonging to a different settlement by guessing IDs.
        // findOrFail on the relation throws ModelNotFoundException when the
        // exception_id belongs to a different settlement.  We catch it here so
        // that both HTTP and in-process (Livewire) callers receive a proper 404
        // JsonResponse rather than an unhandled exception.
        try {
            $exception = $settlement->exceptions()->findOrFail($data['exception_id']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Settlement exception not found.'], 404);
        }

        $key = $data['idempotency_key']
            ?? $request->header('Idempotency-Key')
            ?? 'settlement_exception.resolve.' . $exception->id;

        try {
            $resolved = app(SettlementService::class)->resolveException(
                $exception,
                ExceptionStatus::from($data['resolution_type']),
                $data['resolution_note'],
                $request->user(),
                $key,
            );
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }

        return response()->json($resolved);
    }

    /**
     * POST /api/settlements/{settlement}/re-reconcile
     *
     * Re-reconcile a settlement after all exceptions are resolved
     * (Finance Specialist / Admin).
     *
     * Body:
     *   idempotency_key  string  optional
     *
     * 200 OK  – Settlement JSON (status: RECONCILED)
     * 422     – Open exceptions remain / wrong state
     */
    public function reReconcile(Request $request, Settlement $settlement): JsonResponse
    {
        $key = $request->input('idempotency_key')
            ?? $request->header('Idempotency-Key')
            ?? 'settlement.reconcile.' . $settlement->id;

        try {
            $reconciled = app(SettlementService::class)->reReconcile($settlement, $key);
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }

        return response()->json($reconciled);
    }

    /**
     * GET /api/settlements/{settlement}/statement
     *
     * Download the settlement statement CSV (Finance Specialist / Admin).
     *
     * Returns a file download response (StreamedResponse).
     * 404 if the file cannot be found / generated.
     */
    public function downloadStatement(Request $request, Settlement $settlement): StreamedResponse|BinaryFileResponse|JsonResponse
    {
        try {
            $path = app(SettlementService::class)->exportStatement($settlement);
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }

        return response()->download(storage_path("app/{$path}"));
    }

    // ── Internal helpers ──────────────────────────────────────────────────────

    private function serviceError(\RuntimeException $e): JsonResponse
    {
        $code   = $e->getCode();
        $status = ($code >= 400 && $code < 600) ? $code : 422;
        return response()->json(['message' => $e->getMessage()], $status);
    }
}
