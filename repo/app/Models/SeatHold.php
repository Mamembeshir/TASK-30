<?php

namespace App\Models;

use App\Enums\HoldReleaseReason;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeatHold extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'trip_id',
        'signup_id',
        'held_at',
        'expires_at',
        'released',
        'released_at',
        'release_reason',
    ];

    protected function casts(): array
    {
        return [
            'held_at'        => 'datetime',
            'expires_at'     => 'datetime',
            'released'       => 'boolean',
            'released_at'    => 'datetime',
            'release_reason' => HoldReleaseReason::class,
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function signup(): BelongsTo
    {
        return $this->belongsTo(TripSignup::class, 'signup_id');
    }

    public function isExpired(): bool
    {
        return ! $this->released && $this->expires_at->isPast();
    }
}
