<?php

namespace Database\Factories;

use App\Enums\SettlementStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class SettlementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'settlement_date'       => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'status'                => SettlementStatus::OPEN->value,
            'total_payments_cents'  => 0,
            'total_refunds_cents'   => 0,
            'net_amount_cents'      => 0,
            'expected_amount_cents' => 0,
            'variance_cents'        => 0,
            'version'               => 1,
        ];
    }

    public function reconciled(): static
    {
        return $this->state([
            'status'           => SettlementStatus::RECONCILED->value,
            'closed_at'        => now(),
            'reconciled_at'    => now(),
            'variance_cents'   => 0,
        ]);
    }

    public function withException(int $varianceCents = 500): static
    {
        return $this->state([
            'status'         => SettlementStatus::EXCEPTION->value,
            'variance_cents' => $varianceCents,
            'closed_at'      => now(),
        ]);
    }

    public function forToday(): static
    {
        return $this->state([
            'settlement_date' => now()->toDateString(),
        ]);
    }
}
