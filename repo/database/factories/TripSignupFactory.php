<?php

namespace Database\Factories;

use App\Enums\SignupStatus;
use App\Models\Trip;
use App\Models\TripSignup;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TripSignupFactory extends Factory
{
    protected $model = TripSignup::class;

    public function definition(): array
    {
        return [
            'trip_id'          => Trip::factory(),
            'user_id'          => User::factory(),
            'status'           => SignupStatus::HOLD->value,
            'hold_expires_at'  => now()->addMinutes(10),
            'confirmed_at'     => null,
            'cancelled_at'     => null,
            'payment_id'       => null,
            'idempotency_key'  => Str::uuid()->toString(),
            'version'          => 1,
        ];
    }

    public function confirmed(): static
    {
        return $this->state([
            'status'          => SignupStatus::CONFIRMED->value,
            'confirmed_at'    => now(),
            'hold_expires_at' => null,
        ]);
    }

    public function expired(): static
    {
        return $this->state([
            'status'          => SignupStatus::EXPIRED->value,
            'hold_expires_at' => now()->subMinutes(5),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state([
            'status'       => SignupStatus::CANCELLED->value,
            'cancelled_at' => now(),
        ]);
    }
}
