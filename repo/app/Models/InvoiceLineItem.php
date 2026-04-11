<?php

namespace App\Models;

use App\Enums\LineItemType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLineItem extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'invoice_id',
        'description',
        'amount_cents',
        'line_type',
        'reference_id',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'line_type'    => LineItemType::class,
            'amount_cents' => 'integer',
            'sort_order'   => 'integer',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function formattedAmount(): string
    {
        return formatCurrency($this->amount_cents);
    }
}
