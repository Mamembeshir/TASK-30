<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Traits\HasOptimisticLocking;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory, HasUuids, HasOptimisticLocking;

    protected $fillable = [
        'user_id',
        'invoice_number',
        'total_cents',
        'status',
        'issued_at',
        'due_date',
        'notes',
        'version',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'id'          => 'string',
            'status'      => InvoiceStatus::class,
            'total_cents' => 'integer',
            'issued_at'   => 'datetime',
            'due_date'    => 'date',
            'version'     => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(InvoiceLineItem::class)->orderBy('sort_order');
    }

    public function formattedTotal(): string
    {
        return formatCurrency($this->total_cents);
    }
}
