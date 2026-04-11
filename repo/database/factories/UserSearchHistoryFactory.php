<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserSearchHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserSearchHistoryFactory extends Factory
{
    protected $model = UserSearchHistory::class;

    public function definition(): array
    {
        return [
            'user_id'      => User::factory(),
            'query'        => fake()->words(3, true),
            'filters'      => null,
            'result_count' => fake()->numberBetween(0, 50),
            'searched_at'  => fake()->dateTimeBetween('-30 days', 'now'),
        ];
    }
}
