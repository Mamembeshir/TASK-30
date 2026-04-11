<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Audit Issue 4: Harden the tamper-evident audit log against direct DB mutation.
 *
 * Prior state: `audit_logs` was append-only only at the Eloquent model layer
 * (static::updating / static::deleting throw), and `previous_hash` stored the
 * prior row's hash but there was no self-contained, per-row signature. A
 * connection with DML privileges could bypass the model and tamper silently.
 *
 * This migration hardens the log in two layers:
 *
 *  1. Per-row `row_hash` column — SHA-256 over the canonical payload including
 *     `previous_hash`. A broken chain is therefore detectable by re-computing
 *     row_hash for each row and comparing to the stored value, even when a
 *     single field has been mutated.
 *
 *  2. PostgreSQL BEFORE UPDATE / BEFORE DELETE trigger on `audit_logs` that
 *     raises an exception. This blocks bypass via direct SQL (query builder,
 *     psql, raw DML). The trigger is the authoritative enforcement; the
 *     Eloquent hooks remain as a fast-fail for developers.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Add the per-row immutable signature column. Nullable for the
        //    backfill step; tightened to NOT NULL afterwards.
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('row_hash', 64)->nullable()->after('previous_hash');
        });

        // Backfill row_hash for any existing rows using the model's canonical
        // hash so the formula matches what new inserts will compute. We go
        // through the query builder (not Eloquent save()) to avoid tripping
        // the model's append-only guards on what is really a schema upgrade.
        // The trigger has not yet been installed at this point in `up()`.
        \App\Models\AuditLog::query()
            ->orderBy('created_at')
            ->orderBy('id')
            ->cursor()
            ->each(function (\App\Models\AuditLog $entry) {
                DB::table('audit_logs')
                    ->where('id', $entry->id)
                    ->update(['row_hash' => \App\Models\AuditLog::computeHash($entry)]);
            });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('row_hash', 64)->nullable(false)->change();
        });

        // 2. PostgreSQL triggers enforcing true append-only at the DB layer.
        //    SQLite (used in some unit tests) cannot compile PL/pgSQL, so the
        //    trigger is only installed on pgsql connections.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION audit_logs_prevent_mutation()
                RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'audit_logs is append-only: % is forbidden', TG_OP;
                END;
                $$ LANGUAGE plpgsql;

                DROP TRIGGER IF EXISTS audit_logs_no_update ON audit_logs;
                CREATE TRIGGER audit_logs_no_update
                    BEFORE UPDATE ON audit_logs
                    FOR EACH ROW EXECUTE FUNCTION audit_logs_prevent_mutation();

                DROP TRIGGER IF EXISTS audit_logs_no_delete ON audit_logs;
                CREATE TRIGGER audit_logs_no_delete
                    BEFORE DELETE ON audit_logs
                    FOR EACH ROW EXECUTE FUNCTION audit_logs_prevent_mutation();
            SQL);
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                DROP TRIGGER IF EXISTS audit_logs_no_update ON audit_logs;
                DROP TRIGGER IF EXISTS audit_logs_no_delete ON audit_logs;
                DROP FUNCTION IF EXISTS audit_logs_prevent_mutation();
            SQL);
        }

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn('row_hash');
        });
    }
};
