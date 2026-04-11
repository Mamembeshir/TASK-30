<?php

namespace App\Models;

use App\Enums\SignupStatus;
use App\Traits\HasOptimisticLocking;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TripSignup extends Model
{
    use HasFactory, HasUuids, HasOptimisticLocking;

    protected $fillable = [
        'trip_id', 'user_id', 'status', 'hold_expires_at',
        'confirmed_at', 'cancelled_at', 'payment_id', 'idempotency_key', 'version',
    ];

    protected function casts(): array
    {
        return [
            'status'          => SignupStatus::class,
            'hold_expires_at' => 'datetime',
            'confirmed_at'    => 'datetime',
            'cancelled_at'    => 'datetime',
            'version'         => 'integer',
        ];
    }

    public function trip(): BelongsTo   { return $this->belongsTo(Trip::class); }
    public function user(): BelongsTo   { return $this->belongsTo(User::class); }
    public function payment(): BelongsTo { return $this->belongsTo(Payment::class); }
    public function hold(): HasOne       { return $this->hasOne(SeatHold::class, 'signup_id'); }

    public function isHoldExpired(): bool
    {
        return $this->status === SignupStatus::HOLD
            && $this->hold_expires_at !== null
            && $this->hold_expires_at->isPast();
    }
}
