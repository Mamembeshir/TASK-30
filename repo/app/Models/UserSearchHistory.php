<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSearchHistory extends Model
{
    use HasFactory;

    // No standard created_at/updated_at — only searched_at
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'query',
        'filters',
        'result_count',
        'searched_at',
    ];

    protected function casts(): array
    {
        return [
            'filters'     => 'array',
            'result_count'=> 'integer',
            'searched_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
