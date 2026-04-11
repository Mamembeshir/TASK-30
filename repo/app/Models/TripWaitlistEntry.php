<?php

namespace App\Models;

use App\Enums\WaitlistStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripWaitlistEntry extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'trip_id',
        'user_id',
        'position',
        'status',
        'offered_at',
        'offer_expires_at',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'status'           => WaitlistStatus::class,
            'position'         => 'integer',
            'offered_at'       => 'datetime',
            'offer_expires_at' => 'datetime',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOfferExpired(): bool
    {
        return $this->status === WaitlistStatus::OFFERED
            && $this->offer_expires_at !== null
            && $this->offer_expires_at->isPast();
    }
}
