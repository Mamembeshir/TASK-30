<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Enums\TenderType;
use App\Traits\HasOptimisticLocking;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    use HasFactory, HasUuids, HasOptimisticLocking;

    protected $fillable = [
        'user_id', 'tender_type', 'amount_cents', 'reference_number',
        'status', 'confirmed_at', 'confirmation_event_id',
        'settlement_id', 'idempotency_key', 'version',
    ];

    protected function casts(): array
    {
        return [
            'status'       => PaymentStatus::class,
            'tender_type'  => TenderType::class,
            'amount_cents' => 'integer',
            'confirmed_at' => 'datetime',
            'version'      => 'integer',
        ];
    }

    public function user(): BelongsTo       { return $this->belongsTo(User::class); }
    public function settlement(): BelongsTo  { return $this->belongsTo(Settlement::class); }
    public function refunds(): HasMany       { return $this->hasMany(Refund::class); }

    public function formattedAmount(): string
    {
        return formatCurrency($this->amount_cents);
    }
}
