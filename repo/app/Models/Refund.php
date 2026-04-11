<?php

namespace App\Models;

use App\Enums\RefundStatus;
use App\Enums\RefundType;
use App\Traits\HasOptimisticLocking;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    use HasFactory, HasUuids, HasOptimisticLocking;

    protected $fillable = [
        'payment_id',
        'amount_cents',
        'refund_type',
        'reason',
        'status',
        'approved_by',
        'processed_at',
        'idempotency_key',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'status'       => RefundStatus::class,
            'refund_type'  => RefundType::class,
            'amount_cents' => 'integer',
            'processed_at' => 'datetime',
            'version'      => 'integer',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function formattedAmount(): string
    {
        return formatCurrency($this->amount_cents);
    }
}
