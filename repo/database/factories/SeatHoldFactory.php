<?php

namespace Database\Factories;

use App\Enums\HoldReleaseReason;
use App\Models\SeatHold;
use App\Models\Trip;
use App\Models\TripSignup;
use Illuminate\Database\Eloquent\Factories\Factory;

class SeatHoldFactory extends Factory
{
    protected $model = SeatHold::class;

    public function definition(): array
    {
        return [
            'trip_id'        => Trip::factory(),
            'signup_id'      => TripSignup::factory(),
            'held_at'        => now(),
            'expires_at'     => now()->addMinutes(10),
            'released'       => false,
            'released_at'    => null,
            'release_reason' => null,
        ];
    }

    public function released(HoldReleaseReason $reason = HoldReleaseReason::EXPIRED): static
    {
        return $this->state([
            'released'       => true,
            'released_at'    => now(),
            'release_reason' => $reason->value,
        ]);
    }

    public function expired(): static
    {
        return $this->state([
            'expires_at' => now()->subMinutes(5),
        ]);
    }
}
