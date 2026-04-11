<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit Issue 3 — close the remaining gaps in the universal idempotency
 * contract described in `docs/design.md:70-73`.
 *
 * Prior to this migration, `trip_signups`, `payments`, `refunds`,
 * `membership_orders`, and `trip_waitlist_entries` already carried an
 * `idempotency_key` column and participated in the service-layer contract,
 * but four entry points did not:
 *
 *   - TripService::create              → `trips`
 *   - CredentialingService::submitCase → `credentialing_cases`
 *   - InvoiceService::createInvoice    → `invoices`
 *   - ReviewService::create            → `trip_reviews`
 *
 * A double-submit on any of these could create duplicate rows (duplicate
 * trip drafts, duplicate credentialing cases, duplicate invoices, duplicate
 * reviews). Adding the column here lets each service short-circuit on a
 * matching key before falling through to the existing natural-key guards.
 *
 * Column width (128) matches the standard set by
 * `2026_04_11_000003_widen_idempotency_key_columns.php`. Each column is
 * nullable (historical rows have no key) and unique (concurrent callers
 * passing the same key collapse onto the same row at the DB level).
 */
return new class extends Migration
{
    private array $tables = [
        'trips',
        'credentialing_cases',
        'invoices',
        'trip_reviews',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->string('idempotency_key', 128)->nullable();
                $t->unique('idempotency_key', "{$table}_idempotency_key_unique");
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->dropUnique("{$table}_idempotency_key_unique");
                $t->dropColumn('idempotency_key');
            });
        }
    }
};
