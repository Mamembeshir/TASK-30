<?php

namespace App\Models;

use App\Enums\TripStatus;
use App\Enums\TripDifficulty;
use App\Traits\HasOptimisticLocking;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Trip extends Model
{
    use HasFactory, HasUuids, HasOptimisticLocking;

    protected $fillable = [
        'title', 'description', 'lead_doctor_id', 'specialty', 'destination',
        'start_date', 'end_date', 'difficulty_level', 'prerequisites',
        'total_seats', 'available_seats', 'price_cents', 'status',
        'booking_count', 'average_rating', 'created_by', 'version',
    ];

    protected function casts(): array
    {
        return [
            'status'           => TripStatus::class,
            'difficulty_level' => TripDifficulty::class,
            'start_date'       => 'date',
            'end_date'         => 'date',
            'price_cents'      => 'integer',
            'total_seats'      => 'integer',
            'available_seats'  => 'integer',
            'booking_count'    => 'integer',
            'average_rating'   => 'decimal:2',
            'version'          => 'integer',
        ];
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'lead_doctor_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function signups(): HasMany
    {
        return $this->hasMany(TripSignup::class);
    }

    public function waitlistEntries(): HasMany
    {
        return $this->hasMany(TripWaitlistEntry::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(TripReview::class);
    }

    public function hasAvailableSeats(): bool
    {
        return $this->available_seats > 0;
    }

    public function formattedPrice(): string
    {
        return formatCurrency($this->price_cents);
    }
}
