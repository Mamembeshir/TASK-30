<?php

namespace App\Models;

use App\Enums\MembershipTier;
use App\Traits\HasOptimisticLocking;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MembershipPlan extends Model
{
    use HasFactory, HasUuids, HasOptimisticLocking;

    protected $fillable = [
        'name', 'description', 'price_cents', 'duration_months', 'tier', 'is_active', 'version',
    ];

    protected function casts(): array
    {
        return [
            'tier'            => MembershipTier::class,
            'price_cents'     => 'integer',
            'duration_months' => 'integer',
            'is_active'       => 'boolean',
            'version'         => 'integer',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(MembershipOrder::class, 'plan_id');
    }

    public function formattedPrice(): string
    {
        return formatCurrency($this->price_cents);
    }
}
