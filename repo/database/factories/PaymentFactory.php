<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Enums\TenderType;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'user_id'        => User::factory(),
            'tender_type'    => TenderType::CASH->value,
            'amount_cents'   => $this->faker->numberBetween(1000, 100000),
            'status'         => PaymentStatus::CONFIRMED->value,
            'idempotency_key'=> (string) Str::uuid(),
            'version'        => 1,
        ];
    }

    public function recorded(): static
    {
        return $this->state(['status' => PaymentStatus::RECORDED->value]);
    }

    public function confirmed(): static
    {
        return $this->state([
            'status'       => PaymentStatus::CONFIRMED->value,
            'confirmed_at' => now(),
        ]);
    }
}
