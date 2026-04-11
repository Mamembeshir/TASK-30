<?php

namespace App\Models;

use App\Enums\UserRole as UserRoleEnum;
use App\Enums\UserStatus;
use App\Exceptions\InvalidStatusTransitionException;
use App\Services\AuditService;
use App\Traits\HasOptimisticLocking;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, HasUuids, HasOptimisticLocking, Notifiable;

    protected $fillable = [
        'username',
        'email',
        'password',
        'status',
        'failed_login_count',
        'locked_until',
        'version',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'id'                 => 'string',
            'status'             => UserStatus::class,
            'password'           => 'hashed',
            'locked_until'       => 'datetime',
            'failed_login_count' => 'integer',
            'version'            => 'integer',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    /** @return HasMany<UserRole> */
    public function roles(): HasMany
    {
        return $this->hasMany(UserRole::class);
    }

    public function doctor(): HasOne
    {
        return $this->hasOne(Doctor::class);
    }

    public function membershipOrders(): HasMany
    {
        return $this->hasMany(MembershipOrder::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function tripSignups(): HasMany
    {
        return $this->hasMany(TripSignup::class);
    }

    public function searchHistory(): HasMany
    {
        return $this->hasMany(UserSearchHistory::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'actor_id');
    }

    // ── Role helpers ──────────────────────────────────────────────────────────

    public function hasRole(UserRoleEnum $role): bool
    {
        return $this->roles()->where('role', $role->value)->exists();
    }

    public function addRole(UserRoleEnum $role): void
    {
        if ($this->hasRole($role)) {
            return;
        }

        $this->roles()->create([
            'role'        => $role->value,
            'assigned_at' => now(),
        ]);

        AuditService::record('user.role_added', 'User', $this->id, null, ['role' => $role->value]);
    }

    public function removeRole(UserRoleEnum $role): void
    {
        if (! $this->hasRole($role)) {
            return;
        }

        $this->roles()->where('role', $role->value)->delete();

        AuditService::record('user.role_removed', 'User', $this->id, ['role' => $role->value], null);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(UserRoleEnum::ADMIN);
    }

    public function isDoctor(): bool
    {
        return $this->hasRole(UserRoleEnum::DOCTOR);
    }

    public function isMember(): bool
    {
        return $this->hasRole(UserRoleEnum::MEMBER);
    }

    public function isCredentialingReviewer(): bool
    {
        return $this->hasRole(UserRoleEnum::CREDENTIALING_REVIEWER);
    }

    public function isFinanceSpecialist(): bool
    {
        return $this->hasRole(UserRoleEnum::FINANCE_SPECIALIST);
    }

    /** All role enum values the user holds. */
    public function roleValues(): array
    {
        return $this->roles()->pluck('role')->map(fn ($r) => UserRoleEnum::from($r))->all();
    }

    // ── Status helpers ────────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === UserStatus::ACTIVE;
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    /**
     * Transition to a new status, validating the state machine and writing an audit entry.
     *
     * @throws InvalidStatusTransitionException
     */
    public function transitionStatus(UserStatus $newStatus): void
    {
        if (! $this->status->canTransitionTo($newStatus)) {
            throw new InvalidStatusTransitionException(
                from:   $this->status->value,
                to:     $newStatus->value,
                entity: 'User',
            );
        }

        $from = $this->status;

        $this->status = $newStatus;
        $this->saveWithLock();

        AuditService::transitioned($this, $from->value, $newStatus->value);
    }

    // ── Active membership ─────────────────────────────────────────────────────

    public function activeMembership(): ?MembershipOrder
    {
        return $this->membershipOrders()
            ->where('status', \App\Enums\OrderStatus::PAID)
            ->where('expires_at', '>', now())
            ->latest('starts_at')
            ->first();
    }
}
