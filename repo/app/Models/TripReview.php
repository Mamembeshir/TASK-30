<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use App\Traits\HasOptimisticLocking;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripReview extends Model
{
    use HasFactory, HasUuids, HasOptimisticLocking;

    protected $fillable = [
        'trip_id',
        'user_id',
        'rating',
        'review_text',
        'status',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'status'  => ReviewStatus::class,
            'rating'  => 'integer',
            'version' => 'integer',
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

    public function isVisible(): bool
    {
        return $this->status === ReviewStatus::ACTIVE;
    }
}
