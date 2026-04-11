<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add `idempotency_key` to `trip_waitlist_entries` so `WaitlistService::joinWaitlist`
 * can participate in the universal service-layer idempotency contract.
 *
 * Before this change, `joinWaitlist` relied solely on the natural-key uniqueness
 * of `(trip_id, user_id)` for dedupe. That worked for *the same user retrying*,
 * but it did not match the contract every other mutating service follows
 * (explicit `string $idempotencyKey` parameter + dedupe on the key before the
 * natural-key check). Aligning this method closes the last gap in the universal
 * idempotency contract — every write path now accepts a caller-stable key and
 * deterministically reuses the existing row on retry.
 *
 * Column is nullable because historical rows (created before this migration)
 * do not have a key; the column is unique so that concurrent callers passing
 * the same key collapse onto the same row at the DB level as a second line of
 * defense. The width matches the 128-char standard established in
 * `2026_04_11_000003_widen_idempotency_key_columns.php`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_waitlist_entries', function (Blueprint $table) {
            $table->string('idempotency_key', 128)->nullable()->after('offer_expires_at');
            $table->unique('idempotency_key', 'trip_waitlist_entries_idempotency_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('trip_waitlist_entries', function (Blueprint $table) {
            $table->dropUnique('trip_waitlist_entries_idempotency_key_unique');
            $table->dropColumn('idempotency_key');
        });
    }
};
