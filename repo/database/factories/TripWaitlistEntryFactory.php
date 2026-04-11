<?php

namespace Database\Factories;

use App\Enums\WaitlistStatus;
use App\Models\Trip;
use App\Models\TripWaitlistEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TripWaitlistEntryFactory extends Factory
{
    protected $model = TripWaitlistEntry::class;

    public function definition(): array
    {
        return [
            'trip_id'          => Trip::factory(),
            'user_id'          => User::factory(),
            'position'         => $this->faker->numberBetween(1, 20),
            'status'           => WaitlistStatus::WAITING->value,
            'offered_at'       => null,
            'offer_expires_at' => null,
        ];
    }

    public function offered(): static
    {
        return $this->state([
            'status'           => WaitlistStatus::OFFERED->value,
            'offered_at'       => now(),
            'offer_expires_at' => now()->addMinutes(10),
        ]);
    }

    public function expiredOffer(): static
    {
        return $this->state([
            'status'           => WaitlistStatus::OFFERED->value,
            'offered_at'       => now()->subMinutes(15),
            'offer_expires_at' => now()->subMinutes(5),
        ]);
    }

    public function accepted(): static
    {
        return $this->state(['status' => WaitlistStatus::ACCEPTED->value]);
    }

    public function declined(): static
    {
        return $this->state(['status' => WaitlistStatus::DECLINED->value]);
    }
}
