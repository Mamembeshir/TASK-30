<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SearchTermSeeder extends Seeder
{
    public function run(): void
    {
        $terms = [
            // Specialties
            ['term' => 'Cardiology',            'category' => 'specialty'],
            ['term' => 'Neurology',             'category' => 'specialty'],
            ['term' => 'Orthopedics',           'category' => 'specialty'],
            ['term' => 'Oncology',              'category' => 'specialty'],
            ['term' => 'Emergency Medicine',    'category' => 'specialty'],
            ['term' => 'Family Medicine',       'category' => 'specialty'],
            ['term' => 'Internal Medicine',     'category' => 'specialty'],
            ['term' => 'Pediatrics',            'category' => 'specialty'],
            ['term' => 'Psychiatry',            'category' => 'specialty'],
            ['term' => 'Surgery',               'category' => 'specialty'],
            ['term' => 'Radiology',             'category' => 'specialty'],
            ['term' => 'Anesthesiology',        'category' => 'specialty'],
            ['term' => 'Dermatology',           'category' => 'specialty'],
            ['term' => 'Ophthalmology',         'category' => 'specialty'],
            ['term' => 'Urology',               'category' => 'specialty'],

            // Common destinations
            ['term' => 'Guatemala City',        'category' => 'destination'],
            ['term' => 'Costa Rica',            'category' => 'destination'],
            ['term' => 'Kenya',                 'category' => 'destination'],
            ['term' => 'Peru',                  'category' => 'destination'],
            ['term' => 'Cambodia',              'category' => 'destination'],
            ['term' => 'Haiti',                 'category' => 'destination'],
            ['term' => 'Dominican Republic',    'category' => 'destination'],
            ['term' => 'Honduras',              'category' => 'destination'],

            // Difficulty
            ['term' => 'Easy',                  'category' => 'difficulty'],
            ['term' => 'Moderate',              'category' => 'difficulty'],
            ['term' => 'Challenging',           'category' => 'difficulty'],
        ];

        foreach ($terms as $term) {
            DB::table('search_terms')->insertOrIgnore([
                'term'        => $term['term'],
                'category'    => $term['category'],
                'usage_count' => 0,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }
}
