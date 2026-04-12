<?php

namespace App\Models;

use App\Enums\SettlementStatus;
use App\Traits\HasOptimisticLocking;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Settlement extends Model
{
    use HasFactory, HasUuids, HasOptimisticLocking;

    protected $fillable = [
        'settlement_date', 'status', 'total_payments_cents', 'total_refunds_cents',
        'net_amount_cents', 'expected_amount_cents', 'variance_cents',
        'closed_at', 'reconciled_by', 'reconciled_at', 'statement_file_path', 'version',
    ];

    protected function casts(): array
    {
        return [
            'status'                 => SettlementStatus::class,
            'settlement_date'        => 'date:Y-m-d',
            'total_payments_cents'   => 'integer',
            'total_refunds_cents'    => 'integer',
            'net_amount_cents'       => 'integer',
            'expected_amount_cents'  => 'integer',
            'variance_cents'         => 'integer',
            'closed_at'              => 'datetime',
            'reconciled_at'          => 'datetime',
            'version'                => 'integer',
        ];
    }

    public function reconciledBy(): BelongsTo { return $this->belongsTo(User::class, 'reconciled_by'); }
    public function payments(): HasMany        { return $this->hasMany(Payment::class); }
    public function exceptions(): HasMany      { return $this->hasMany(SettlementException::class); }

    public function hasVariance(): bool
    {
        return abs($this->variance_cents) > 1; // > $0.01
    }
}
