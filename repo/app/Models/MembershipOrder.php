<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Traits\HasOptimisticLocking;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MembershipOrder extends Model
{
    use HasFactory, HasUuids, HasOptimisticLocking;

    protected $fillable = [
        'user_id', 'plan_id', 'order_type', 'amount_cents', 'previous_order_id',
        'status', 'starts_at', 'expires_at', 'top_up_eligible_until',
        'payment_id', 'idempotency_key', 'version',
    ];

    protected function casts(): array
    {
        return [
            'status'               => OrderStatus::class,
            'order_type'           => OrderType::class,
            'amount_cents'         => 'integer',
            'starts_at'            => 'datetime',
            'expires_at'           => 'datetime',
            'top_up_eligible_until'=> 'datetime',
            'version'              => 'integer',
        ];
    }

    public function user(): BelongsTo         { return $this->belongsTo(User::class); }
    public function plan(): BelongsTo         { return $this->belongsTo(MembershipPlan::class); }
    public function payment(): BelongsTo      { return $this->belongsTo(Payment::class); }
    public function previousOrder(): BelongsTo { return $this->belongsTo(self::class, 'previous_order_id'); }

    public function isActive(): bool
    {
        return $this->status === OrderStatus::PAID && $this->expires_at->isFuture();
    }

    public function isTopUpEligible(): bool
    {
        return $this->isActive()
            && $this->top_up_eligible_until !== null
            && $this->top_up_eligible_until->isFuture();
    }

    public function formattedAmount(): string
    {
        return formatCurrency($this->amount_cents);
    }
}
