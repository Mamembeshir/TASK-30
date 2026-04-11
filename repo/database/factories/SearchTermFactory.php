<?php

namespace Database\Factories;

use App\Models\SearchTerm;
use Illuminate\Database\Eloquent\Factories\Factory;

class SearchTermFactory extends Factory
{
    protected $model = SearchTerm::class;

    public function definition(): array
    {
        return [
            'term'        => mb_strtolower(fake()->unique()->word()),
            'category'    => fake()->randomElement(['specialty', 'destination', 'title', 'general']),
            'usage_count' => fake()->numberBetween(0, 500),
        ];
    }

    public function popular(): static
    {
        return $this->state(['usage_count' => fake()->numberBetween(100, 1000)]);
    }

    public function specialty(string $term): static
    {
        return $this->state(['term' => mb_strtolower($term), 'category' => 'specialty']);
    }
}
