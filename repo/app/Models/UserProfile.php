<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    protected $primaryKey = 'user_id';
    public $incrementing  = false;
    protected $keyType    = 'string';

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'date_of_birth',
        'phone',
        'address_encrypted',
        'address_mask',
        'ssn_fragment_encrypted',
        'ssn_fragment_mask',
    ];

    protected $hidden = [
        'address_encrypted',
        'ssn_fragment_encrypted',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
