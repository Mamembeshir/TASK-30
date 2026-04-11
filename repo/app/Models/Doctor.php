<?php

namespace App\Models;

use App\Enums\CredentialingStatus;
use App\Services\AuditService;
use App\Traits\HasOptimisticLocking;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Doctor extends Model
{
    use HasFactory, HasUuids, HasOptimisticLocking;

    protected $fillable = [
        'user_id', 'specialty', 'npi_number',
        'license_number_encrypted', 'license_number_mask',
        'license_state', 'license_expiry',
        'credentialing_status', 'activated_at', 'version',
    ];

    protected $hidden = ['license_number_encrypted'];

    protected function casts(): array
    {
        return [
            'credentialing_status' => CredentialingStatus::class,
            'license_expiry'       => 'date',
            'activated_at'         => 'datetime',
            'version'              => 'integer',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(DoctorDocument::class)->orderByDesc('uploaded_at');
    }

    public function credentialingCases(): HasMany
    {
        return $this->hasMany(CredentialingCase::class);
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class, 'lead_doctor_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isApproved(): bool
    {
        return $this->credentialing_status === CredentialingStatus::APPROVED;
    }

    public function isLicenseExpired(): bool
    {
        return $this->license_expiry !== null && $this->license_expiry->isPast();
    }

    /** Returns the one non-terminal case, if any. */
    public function activeCase(): ?CredentialingCase
    {
        return $this->credentialingCases()
            ->whereNotIn('status', [
                CredentialingStatus::APPROVED->value,
                CredentialingStatus::REJECTED->value,
            ])
            ->latest()
            ->first();
    }

    public function hasRequiredDocuments(): bool
    {
        $types = $this->documents()
            ->pluck('document_type')
            ->map(fn ($t) => $t instanceof \App\Enums\DocumentType ? $t->value : $t)
            ->toArray();

        return in_array(\App\Enums\DocumentType::LICENSE->value, $types)
            && in_array(\App\Enums\DocumentType::BOARD_CERTIFICATION->value, $types);
    }

    /**
     * Transition credentialing status, enforcing PRD 10.3 and writing an audit entry.
     */
    public function transitionCredentialingStatus(CredentialingStatus $newStatus): void
    {
        $from = $this->credentialing_status;
        $this->credentialing_status = $newStatus;

        if ($newStatus === CredentialingStatus::APPROVED) {
            $this->activated_at = now();
        }

        $this->saveWithLock();

        AuditService::record(
            'doctor.credentialing_status_changed',
            'Doctor',
            $this->id,
            ['credentialing_status' => $from->value],
            ['credentialing_status' => $newStatus->value],
        );
    }
}
