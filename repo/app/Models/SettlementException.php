<?php

namespace App\Models;

use App\Enums\ExceptionStatus;
use App\Enums\ExceptionType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SettlementException extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'settlement_id',
        'exception_type',
        'description',
        'amount_cents',
        'status',
        'resolved_by',
        'resolution_note',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'exception_type' => ExceptionType::class,
            'status'         => ExceptionStatus::class,
            'amount_cents'   => 'integer',
            'version'        => 'integer',
        ];
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(Settlement::class);
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
