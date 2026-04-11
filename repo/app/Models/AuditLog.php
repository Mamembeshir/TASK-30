<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only audit log.
 *
 * Rules enforced at the model level:
 *  - No update()     → throws LogicException
 *  - No delete()     → throws LogicException
 *  - No soft deletes
 *  - Hash-chaining:  each record stores previous_hash = SHA-256(prev.id + prev.action + prev.entity_id + prev.timestamp + prev.actor_id)
 */
class AuditLog extends Model
{
    use HasUuids;

    public const UPDATED_AT = null; // Append-only — no updated_at

    protected $table = 'audit_logs';

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
        'previous_hash',
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

    // ── Append-only enforcement ───────────────────────────────────────────────

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \LogicException('AuditLog records are append-only and cannot be updated.');
        });

        static::deleting(function () {
            throw new \LogicException('AuditLog records are append-only and cannot be deleted.');
        });
    }

    // ── Hash chaining ─────────────────────────────────────────────────────────

    public static function computeHash(self $entry): string
    {
        $payload = implode('|', [
            $entry->id,
            $entry->action,
            $entry->entity_id ?? '',
            $entry->created_at?->toIso8601String() ?? '',
            $entry->actor_id ?? '',
        ]);
        return hash('sha256', $payload);
    }

    public static function latestHash(): ?string
    {
        $latest = static::latest('created_at')->first();
        return $latest ? static::computeHash($latest) : null;
    }

    // ── Factory ───────────────────────────────────────────────────────────────

    /**
     * Record an audit entry.
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
            'previous_hash'   => static::latestHash(),
        ]);
    }
}
