<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Stores idempotency keys for POST/PUT requests.
 * Keys expire after 24 hours (cleaned up by scheduled job).
 */
class IdempotencyRecord extends Model
{
    use HasUuids;

    protected $table = 'idempotency_records';

    public const UPDATED_AT = null;

    protected $fillable = [
        'idempotency_key',
        'endpoint',
        'response_status',
        'response_body',
        'expires_at',
    ];

    protected $casts = [
        'response_body' => 'array',
        'expires_at'    => 'datetime',
        'created_at'    => 'datetime',
    ];

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeValid($query)
    {
        return $query->where('expires_at', '>', now());
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public static function findValid(string $key, string $endpoint): ?static
    {
        return static::valid()
            ->where('idempotency_key', $key)
            ->where('endpoint', $endpoint)
            ->first();
    }

    public static function store(string $key, string $endpoint, int $status, mixed $body): static
    {
        return static::create([
            'idempotency_key' => $key,
            'endpoint'        => $endpoint,
            'response_status' => $status,
            'response_body'   => $body,
            'expires_at'      => now()->addHours(24),
        ]);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
