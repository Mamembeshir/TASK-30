<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Lightweight store for operation-level idempotency keys.
 *
 * Covers state-transition (mutating) operations that cannot use the
 * per-resource `idempotency_key` column approach (which is appropriate
 * only for "create a new resource" paths). The store is backed by the
 * `operation_idempotency_records` table added in migration
 * `2026_04_11_000007_create_operation_idempotency_records`.
 *
 * Usage pattern in a service method:
 *
 *   $store = new IdempotencyStore();
 *   if ($idempotencyKey && $store->alreadyProcessed($idempotencyKey, 'credentialing.approve', $case->id)) {
 *       return $case->fresh();
 *   }
 *   // … perform the state transition …
 *   if ($idempotencyKey) {
 *       $store->record($idempotencyKey, 'credentialing.approve', 'CredentialingCase', $case->id);
 *   }
 */
class IdempotencyStore
{
    private const TTL_HOURS = 24;

    /**
     * Return true if this (key, operation) pair was already completed
     * successfully. Expired records are ignored.
     */
    public function alreadyProcessed(string $key, string $operation, string $entityId): bool
    {
        return DB::table('operation_idempotency_records')
            ->where('idempotency_key', $key)
            ->where('operation', $operation)
            ->where('entity_id', $entityId)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->exists();
    }

    /**
     * Persist a successful operation so that retries short-circuit.
     *
     * Uses `insertOrIgnore` so a concurrent request that won the race
     * does not cause this one to throw — the unique constraint violation
     * is silently dropped and the caller sees the entity's current state.
     */
    public function record(
        string  $key,
        string  $operation,
        string  $entityType,
        string  $entityId,
    ): void {
        DB::table('operation_idempotency_records')->insertOrIgnore([
            'id'               => (string) \Illuminate\Support\Str::uuid(),
            'idempotency_key'  => $key,
            'operation'        => $operation,
            'entity_type'      => $entityType,
            'entity_id'        => $entityId,
            'expires_at'       => now()->addHours(self::TTL_HOURS),
            'created_at'       => now(),
        ]);
    }
}
