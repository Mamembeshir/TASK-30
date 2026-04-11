<?php

/**
 * Audit Issue 4: tamper-evidence regression suite.
 *
 * These tests are the safety net for the three defensive layers around
 * `audit_logs`:
 *
 *  1. The model-layer append-only hooks (cannot save(), cannot delete()).
 *  2. The PostgreSQL BEFORE UPDATE/DELETE trigger installed by the
 *     2026_04_11_000006 migration (blocks bypass via raw query builder).
 *  3. The per-row `row_hash` chain, verified end-to-end by
 *     `medvoyage:verify-audit-chain`.
 *
 * If any of these regress, one or more of these tests will fail.
 */

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// ── Model-layer guards ────────────────────────────────────────────────────────

it('rejects updates via the model layer', function () {
    $admin = User::factory()->create();
    $entry = AuditLog::record($admin->id, 'action.update-attempt', 'X', null);

    expect(fn () => $entry->update(['action' => 'action.tampered']))
        ->toThrow(\LogicException::class, 'append-only');
});

it('rejects deletes via the model layer', function () {
    $admin = User::factory()->create();
    $entry = AuditLog::record($admin->id, 'action.delete-attempt', 'X', null);

    expect(fn () => $entry->delete())
        ->toThrow(\LogicException::class, 'append-only');
});

// ── DB-layer guards (PostgreSQL trigger) ─────────────────────────────────────

it('rejects raw UPDATE on audit_logs at the database layer', function () {
    $admin = User::factory()->create();
    $entry = AuditLog::record($admin->id, 'action.raw-update', 'X', null);

    // Bypass Eloquent entirely. The PG trigger must still stop us.
    //
    // We wrap the raw mutation in DB::transaction() so Laravel issues a
    // SAVEPOINT inside the RefreshDatabase outer transaction. When the PG
    // trigger raises, only the savepoint is rolled back — the outer test
    // transaction stays usable for the follow-up assertion query. Without
    // this, Postgres leaves the outer transaction in 25P02 (aborted) state
    // and every subsequent SELECT fails with "current transaction is
    // aborted, commands ignored".
    expect(fn () => DB::transaction(fn () => DB::table('audit_logs')
        ->where('id', $entry->id)
        ->update(['action' => 'action.forged'])
    ))->toThrow(\Illuminate\Database\QueryException::class, 'append-only');

    // Row must be unchanged.
    expect(AuditLog::find($entry->id)->action)->toBe('action.raw-update');
});

it('rejects raw DELETE on audit_logs at the database layer', function () {
    $admin = User::factory()->create();
    $entry = AuditLog::record($admin->id, 'action.raw-delete', 'X', null);

    expect(fn () => DB::transaction(fn () => DB::table('audit_logs')
        ->where('id', $entry->id)
        ->delete()
    ))->toThrow(\Illuminate\Database\QueryException::class, 'append-only');

    expect(AuditLog::find($entry->id))->not->toBeNull();
});

// ── Hash chain integrity ─────────────────────────────────────────────────────

it('links previous_hash to the prior row row_hash', function () {
    $admin = User::factory()->create();

    $a = AuditLog::record($admin->id, 'action.a', 'X', null);
    $b = AuditLog::record($admin->id, 'action.b', 'X', null);
    $c = AuditLog::record($admin->id, 'action.c', 'X', null);

    expect($a->fresh()->previous_hash)->toBeNull();
    expect($b->fresh()->previous_hash)->toBe($a->fresh()->row_hash);
    expect($c->fresh()->previous_hash)->toBe($b->fresh()->row_hash);
});

it('row_hash is deterministic on fresh re-computation', function () {
    $admin = User::factory()->create();
    $entry = AuditLog::record($admin->id, 'action.determ', 'X', null);

    expect(AuditLog::computeHash($entry->fresh()))->toBe($entry->fresh()->row_hash);
});

// ── Verification artisan command ─────────────────────────────────────────────

it('medvoyage:verify-audit-chain passes on a clean chain', function () {
    $admin = User::factory()->create();
    AuditLog::record($admin->id, 'action.1', 'X', null);
    AuditLog::record($admin->id, 'action.2', 'X', null);
    AuditLog::record($admin->id, 'action.3', 'X', null);

    $this->artisan('medvoyage:verify-audit-chain')
         ->expectsOutputToContain('Audit chain OK')
         ->assertExitCode(0);
});

it('medvoyage:verify-audit-chain detects a field mutation', function () {
    $admin = User::factory()->create();
    $a = AuditLog::record($admin->id, 'action.1', 'X', null);
    AuditLog::record($admin->id, 'action.2', 'X', null);

    // Tamper by going under the trigger: drop it temporarily, mutate the
    // action column, put it back. This simulates an attacker with DDL
    // privileges, which is the exact threat model the row_hash defends
    // against — once the triggers are bypassed, only the cryptographic
    // chain catches the forgery.
    DB::unprepared('DROP TRIGGER audit_logs_no_update ON audit_logs;');
    DB::table('audit_logs')->where('id', $a->id)->update(['action' => 'action.forged']);
    DB::unprepared(<<<'SQL'
        CREATE TRIGGER audit_logs_no_update
            BEFORE UPDATE ON audit_logs
            FOR EACH ROW EXECUTE FUNCTION audit_logs_prevent_mutation();
    SQL);

    $this->artisan('medvoyage:verify-audit-chain')
         ->expectsOutputToContain('Audit chain verification FAILED')
         ->expectsOutputToContain('row_hash does not match')
         ->assertExitCode(1);
});
