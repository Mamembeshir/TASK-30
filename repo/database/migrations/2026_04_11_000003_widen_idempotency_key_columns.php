<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Widen every `idempotency_key` column from varchar(64) to varchar(128).
 *
 * The original 64-char width was tight: it only fits a single UUID plus a
 * short prefix (e.g. `pay-signup-{uuid}` = 47 chars). The deterministic-key
 * contract enforced by the service layer needs to encode *multiple* stable
 * identifiers so that retries from different sources collapse onto the
 * correct row — for example:
 *
 *   - `hold-{userId}-{tripId}`                (78 chars, over the old limit)
 *   - `refund-order-{orderId}-{userId}`       (86 chars, over the old limit)
 *
 * Truncation on insert surfaces as SQLSTATE[22001] "value too long for type
 * character varying(64)" and breaks the write entirely. Widening to 128 gives
 * callers headroom for two UUIDs plus a descriptive prefix without forcing a
 * hash — keys stay human-readable in logs, audit entries, and debugging.
 *
 * Postgres varchar has no storage penalty for unused capacity, so this is a
 * free change for rows that still use shorter keys.
 *
 * Affected tables:
 *   - trip_signups.idempotency_key         (unique)
 *   - membership_orders.idempotency_key    (unique)
 *   - payments.idempotency_key             (unique)
 *   - refunds.idempotency_key              (unique)
 *   - audit_logs.idempotency_key           (nullable, non-unique)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_signups', function (Blueprint $table) {
            $table->string('idempotency_key', 128)->change();
        });

        Schema::table('membership_orders', function (Blueprint $table) {
            $table->string('idempotency_key', 128)->change();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('idempotency_key', 128)->change();
        });

        Schema::table('refunds', function (Blueprint $table) {
            $table->string('idempotency_key', 128)->change();
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('idempotency_key', 128)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('trip_signups', function (Blueprint $table) {
            $table->string('idempotency_key', 64)->change();
        });

        Schema::table('membership_orders', function (Blueprint $table) {
            $table->string('idempotency_key', 64)->change();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('idempotency_key', 64)->change();
        });

        Schema::table('refunds', function (Blueprint $table) {
            $table->string('idempotency_key', 64)->change();
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('idempotency_key', 64)->nullable()->change();
        });
    }
};
