<?php

namespace App\Models;

use App\Enums\CaseAction;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CredentialingAction extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'credentialing_actions';

    protected $fillable = [
        'case_id',
        'action',
        'actor_id',
        'notes',
        'timestamp',
    ];

    protected $casts = [
        'action'    => CaseAction::class,
        'timestamp' => 'datetime',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(CredentialingCase::class, 'case_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
