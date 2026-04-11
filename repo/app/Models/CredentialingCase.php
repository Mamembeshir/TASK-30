<?php

namespace App\Models;

use App\Enums\CaseStatus;
use App\Traits\HasOptimisticLocking;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CredentialingCase extends Model
{
    use HasFactory, HasUuids, HasOptimisticLocking;

    protected $table = 'credentialing_cases';

    protected $fillable = [
        'doctor_id',
        'status',
        'assigned_reviewer',
        'submitted_at',
        'resolved_at',
        'version',
        'idempotency_key',
    ];

    protected $casts = [
        'status'       => CaseStatus::class,
        'submitted_at' => 'datetime',
        'resolved_at'  => 'datetime',
        'version'      => 'integer',
    ];

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_reviewer');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(CredentialingAction::class, 'case_id')->orderBy('timestamp');
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    public function canBeActedOnBy(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }
        return $this->assigned_reviewer !== null && $this->assigned_reviewer === $user->id;
    }
}
