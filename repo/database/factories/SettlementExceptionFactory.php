<?php

namespace Database\Factories;

use App\Enums\ExceptionStatus;
use App\Enums\ExceptionType;
use App\Models\Settlement;
use App\Models\SettlementException;
use Illuminate\Database\Eloquent\Factories\Factory;

class SettlementExceptionFactory extends Factory
{
    protected $model = SettlementException::class;

    public function definition(): array
    {
        return [
            'settlement_id'   => Settlement::factory(),
            'exception_type'  => ExceptionType::VARIANCE->value,
            'description'     => fake()->sentence(),
            'amount_cents'    => fake()->numberBetween(100, 10000),
            'status'          => ExceptionStatus::OPEN->value,
            'version'         => 1,
        ];
    }

    public function resolved(): static
    {
        return $this->state(['status' => ExceptionStatus::RESOLVED->value]);
    }

    public function writtenOff(): static
    {
        return $this->state(['status' => ExceptionStatus::WRITTEN_OFF->value]);
    }
}
