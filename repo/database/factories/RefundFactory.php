<?php

namespace Database\Factories;

use App\Enums\RefundStatus;
use App\Enums\RefundType;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class RefundFactory extends Factory
{
    protected $model = Refund::class;

    public function definition(): array
    {
        return [
            'payment_id'      => Payment::factory(),
            'amount_cents'    => $this->faker->numberBetween(500, 9900),
            'refund_type'     => RefundType::FULL->value,
            'reason'          => $this->faker->sentence(10),
            'status'          => RefundStatus::PENDING->value,
            'idempotency_key' => (string) Str::uuid(),
            'version'         => 1,
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => RefundStatus::PENDING->value]);
    }

    public function approved(): static
    {
        return $this->state(['status' => RefundStatus::APPROVED->value]);
    }

    public function processed(): static
    {
        return $this->state([
            'status'       => RefundStatus::PROCESSED->value,
            'processed_at' => now(),
        ]);
    }

    public function partial(): static
    {
        return $this->state(['refund_type' => RefundType::PARTIAL->value]);
    }
}
