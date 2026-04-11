<?php

namespace Database\Factories;

use App\Enums\ReviewStatus;
use App\Models\Trip;
use App\Models\TripReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TripReviewFactory extends Factory
{
    protected $model = TripReview::class;

    public function definition(): array
    {
        return [
            'trip_id'     => Trip::factory(),
            'user_id'     => User::factory(),
            'rating'      => $this->faker->numberBetween(1, 5),
            'review_text' => $this->faker->optional(0.8)->paragraph(),
            'status'      => ReviewStatus::ACTIVE->value,
            'version'     => 1,
        ];
    }

    public function flagged(): static
    {
        return $this->state(['status' => ReviewStatus::FLAGGED->value]);
    }

    public function removed(): static
    {
        return $this->state(['status' => ReviewStatus::REMOVED->value]);
    }

    public function withRating(int $rating): static
    {
        return $this->state(['rating' => $rating]);
    }
}
