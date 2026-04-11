<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit Issue 2 — universal idempotency for state-transition (mutating)
 * service methods.
 *
 * Create-path idempotency was already handled by `idempotency_key` columns
 * on the resource tables themselves (trips, credentialing_cases, invoices,
 * trip_reviews). That pattern works well for "create a new resource" because
 * the key lives with the created row.
 *
 * State-transition operations (approve, reject, assignReviewer, issueInvoice,
 * markPaid, voidInvoice, resolveException, reReconcile …) cannot store the
 * key on the resource without one column per operation (unmaintainable). A
 * single append-only side-table keyed by (idempotency_key, operation) is the
 * standard pattern for this scenario.
 *
 * Semantics
 * ---------
 *  • A row is written AFTER a successful transition.
 *  • A retry with the same (key, operation) pair short-circuits before
 *    re-running the transition and returns the current model state.
 *  • The unique constraint on (idempotency_key, operation) prevents two
 *    concurrent requests with the same key from both running the transition.
 *  • Keys expire after 24 h (matches the CLAUDE.md contract); a scheduled
 *    command or DB job can prune stale rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operation_idempotency_records', function (Blueprint $table) {
            // UUID is always supplied by the application layer (IdempotencyStore::record
            // passes (string) Str::uuid()). No DB-level default is needed, which also
            // removes any dependency on the pgcrypto extension (gen_random_uuid()).
            $table->uuid('id')->primary();
            $table->string('idempotency_key', 128);
            // Dot-namespaced operation identifier, e.g. "credentialing.approve"
            $table->string('operation', 128);
            // The entity the operation acted on, for debugging
            $table->string('entity_type', 128)->nullable();
            $table->uuid('entity_id')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['idempotency_key', 'operation'], 'oipr_key_operation_unique');
            $table->index('expires_at', 'oipr_expires_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_idempotency_records');
    }
};
