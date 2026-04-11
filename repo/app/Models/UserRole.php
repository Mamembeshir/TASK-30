<?php

namespace App\Models;

use App\Enums\UserRole as UserRoleEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent model for the user_roles pivot table.
 *
 * Note: App\Enums\UserRole is the backed enum for role values.
 *       This model represents a row in the user_roles table.
 */
class UserRole extends Model
{
    public $timestamps = false;

    protected $table = 'user_roles';

    protected $fillable = [
        'user_id',
        'role',
        'assigned_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function roleEnum(): UserRoleEnum
    {
        return UserRoleEnum::from($this->role);
    }
}
