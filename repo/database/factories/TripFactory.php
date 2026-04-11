<?php

namespace Database\Factories;

use App\Enums\TripDifficulty;
use App\Enums\TripStatus;
use App\Models\Doctor;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TripFactory extends Factory
{
    protected $model = Trip::class;

    public function definition(): array
    {
        $totalSeats = $this->faker->numberBetween(5, 30);
        $start      = $this->faker->dateTimeBetween('+1 month', '+6 months');
        $end        = \Carbon\Carbon::instance($start)->addDays($this->faker->numberBetween(5, 21));

        return [
            'title'            => $this->faker->sentence(4),
            'description'      => $this->faker->paragraphs(2, true),
            'lead_doctor_id'   => Doctor::factory(),
            'specialty'        => $this->faker->randomElement(['Cardiology', 'Surgery', 'Orthopedics', 'Ophthalmology']),
            'destination'      => $this->faker->city() . ', ' . $this->faker->country(),
            'start_date'       => $start,
            'end_date'         => $end,
            'difficulty_level' => $this->faker->randomElement(TripDifficulty::cases())->value,
            'prerequisites'    => $this->faker->optional()->sentence(),
            'total_seats'      => $totalSeats,
            'available_seats'  => $totalSeats,
            'price_cents'      => $this->faker->numberBetween(50000, 500000),
            'status'           => TripStatus::DRAFT->value,
            'booking_count'    => 0,
            'average_rating'   => null,
            'created_by'       => User::factory(),
            'version'          => 1,
        ];
    }

    public function published(): static
    {
        return $this->state(['status' => TripStatus::PUBLISHED->value]);
    }

    public function full(): static
    {
        return $this->state(fn (array $attrs) => [
            'status'          => TripStatus::FULL->value,
            'available_seats' => 0,
        ]);
    }

    public function withSeats(int $total, int $available): static
    {
        return $this->state([
            'total_seats'     => $total,
            'available_seats' => $available,
        ]);
    }
}
