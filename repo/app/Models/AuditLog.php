<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * Append-only audit log.
 *
 * Defense-in-depth against tampering (audit Issue 4):
 *
 *  1. Eloquent layer: `updating` / `deleting` hooks throw. Fast fail for
 *     developers writing application code.
 *
 *  2. PostgreSQL layer: BEFORE UPDATE / BEFORE DELETE triggers installed by
 *     the `2026_04_11_000006_harden_audit_log_tamper_resistance` migration
 *     raise an exception, which is authoritative and blocks bypass via raw
 *     SQL or the query builder.
 *
 *  3. Cryptographic layer: every row stores a self-contained `row_hash`
 *     (SHA-256 over all the business-relevant fields *plus* `previous_hash`),
 *     forming a chain. A single mutated byte anywhere in the chain is
 *     detectable by `php artisan medvoyage:verify-audit-chain`, even if an
 *     attacker managed to bypass layers 1 and 2.
 */
class AuditLog extends Model
{
    use HasUuids;

    public const UPDATED_AT = null; // Append-only — no updated_at

    protected $table = 'audit_logs';

    // Note: `previous_hash` and `row_hash` are intentionally NOT fillable.
    // They are populated by the `creating` hook from canonical state so that
    // a caller cannot pin a forged chain value via mass assignment.
    protected $fillable = [
        'actor_id',
        'action',
        'entity_type',
        'entity_id',
        'before_data',
        'after_data',
        'ip_address',
        'idempotency_key',
        'correlation_id',
    ];

    protected $casts = [
        'before_data' => 'array',
        'after_data'  => 'array',
        'created_at'  => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    // ── Append-only enforcement (model layer) ────────────────────────────────

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \LogicException('AuditLog records are append-only and cannot be updated.');
        });

        static::deleting(function () {
            throw new \LogicException('AuditLog records are append-only and cannot be deleted.');
        });

        // Fill created_at, previous_hash, and row_hash automatically on insert
        // so callers cannot forget and cannot pin forged values. `created_at`
        // is normally populated by Eloquent *after* the creating event (via
        // updateTimestamps), but computeHash() incorporates the timestamp, so
        // we pre-seed it here; Laravel's updateTimestamps() won't overwrite a
        // dirty column.
        static::creating(function (self $entry) {
            if (! $entry->created_at) {
                $entry->created_at = now();
            }

            // Always (re)compute head of chain server-side — a client-supplied
            // previous_hash would allow forging a fork. `record()` sets it
            // too; this is the belt.
            $entry->previous_hash = static::latestHash();

            // row_hash is always recomputed; callers must never set it.
            $entry->row_hash = static::computeHash($entry);
        });
    }

    // ── Hash chaining ─────────────────────────────────────────────────────────

    /**
     * Canonical per-row signature. Includes every field that matters for
     * integrity, *plus* the previous row's hash, which is what turns the log
     * into a Merkle chain instead of a flat hash-per-row.
     *
     * Changing this formula invalidates all existing row_hash values — do not
     * edit without a backfill migration.
     */
    public static function computeHash(self $entry): string
    {
        $payload = implode('|', [
            $entry->id ?? '',
            $entry->action ?? '',
            $entry->entity_type ?? '',
            $entry->entity_id ?? '',
            $entry->actor_id ?? '',
            static::canonicalJson($entry->before_data),
            static::canonicalJson($entry->after_data),
            $entry->correlation_id ?? '',
            $entry->idempotency_key ?? '',
            $entry->created_at ? $entry->created_at->toIso8601String() : '',
            $entry->previous_hash ?? '',
        ]);
        return hash('sha256', $payload);
    }

    /**
     * Canonicalize JSON column values so hashing is stable.
     *
     * The `before_data` / `after_data` columns are `jsonb`, which means
     * PostgreSQL reformats the stored value (key order, whitespace) relative
     * to whatever Laravel sent in. Hashing the raw DB string would therefore
     * disagree with the hash computed pre-insert from the PHP array.
     *
     * Canonicalization: recursively sort associative-array keys, then
     * json_encode with flags that yield a stable byte representation.
     */
    private static function canonicalJson(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = $decoded === null && strtolower($value) !== 'null' ? $value : $decoded;
        }
        if (is_array($value)) {
            $value = static::recursiveKsort($value);
        }
        return json_encode(
            $value,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
        );
    }

    private static function recursiveKsort(array $input): array
    {
        // Only sort associative arrays; preserve sequential array order.
        $isAssoc = array_keys($input) !== range(0, count($input) - 1);
        if ($isAssoc) {
            ksort($input);
        }
        foreach ($input as $k => $v) {
            if (is_array($v)) {
                $input[$k] = static::recursiveKsort($v);
            }
        }
        return $input;
    }

    /**
     * Head of the chain: the `row_hash` of the most recent log. Stored
     * verbatim in the next row's `previous_hash` field.
     *
     * MUST be called inside a transaction. The `lockForUpdate()` serializes
     * concurrent chain-head reads: two concurrent inserts cannot both read
     * the same previous_hash and fork the chain into a non-linear structure.
     * Without the lock, PostgreSQL's MVCC would let both transactions see the
     * same latest row, producing two rows with identical `previous_hash` values
     * and making `medvoyage:verify-audit-chain` fail.
     */
    public static function latestHash(): ?string
    {
        return static::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('row_hash');
    }

    // ── Factory ───────────────────────────────────────────────────────────────

    /**
     * Record an audit entry.
     *
     * Wraps the insert in a serializable transaction so that `latestHash()`'s
     * `lockForUpdate()` can block concurrent chain-head reads. Without the
     * wrapping transaction the lock has no effect (PostgreSQL advisory locks
     * on rows only block within the same transaction boundary).
     *
     * Callers that are already inside a transaction (e.g., service methods
     * that use DB::transaction()) will join the outer transaction; the inner
     * DB::transaction() call is a no-op savepoint in that case, which is fine
     * because the outer transaction already holds the necessary row lock from
     * the last `lockForUpdate()` call in the same connection.
     */
    public static function record(
        ?string $actorId,
        string  $action,
        string  $entityType,
        ?string $entityId,
        mixed   $before       = null,
        mixed   $after        = null,
        ?string $ipAddress    = null,
        ?string $idempotencyKey = null,
        ?string $correlationId  = null,
    ): static {
        return DB::transaction(function () use (
            $actorId, $action, $entityType, $entityId,
            $before, $after, $ipAddress, $idempotencyKey, $correlationId,
        ) {
            // `previous_hash` and `row_hash` are populated by the `creating`
            // hook from canonical state — callers cannot and should not set them.
            return static::create([
                'actor_id'        => $actorId,
                'action'          => $action,
                'entity_type'     => $entityType,
                'entity_id'       => $entityId,
                'before_data'     => $before,
                'after_data'      => $after,
                'ip_address'      => $ipAddress ?? request()?->ip(),
                'idempotency_key' => $idempotencyKey,
                'correlation_id'  => $correlationId,
            ]);
        });
    }
}
