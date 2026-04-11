<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SearchTerm extends Model
{
    use HasFactory;

    protected $fillable = [
        'term',
        'category',
        'usage_count',
    ];

    protected function casts(): array
    {
        return [
            'usage_count' => 'integer',
        ];
    }

    /**
     * Case-insensitive upsert: insert or increment usage_count.
     */
    public static function upsertTerm(string $term, string $category = 'general'): void
    {
        $term = mb_strtolower(trim($term));
        if ($term === '') {
            return;
        }

        static::updateOrCreate(
            ['term' => $term],
            ['category' => $category, 'usage_count' => 0]
        );
    }

    /**
     * Increment usage count for a matched term.
     */
    public static function touchTerm(string $term): void
    {
        static::where('term', mb_strtolower(trim($term)))
            ->increment('usage_count');
    }
}
