<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        static $seq = 0;
        $seq++;
        $year = now()->year;

        return [
            'user_id'        => User::factory(),
            'invoice_number' => sprintf('MV-%d-%05d', $year, $seq),
            'total_cents'    => fake()->numberBetween(1000, 50000),
            'status'         => InvoiceStatus::DRAFT->value,
            'version'        => 1,
        ];
    }

    public function issued(): static
    {
        return $this->state([
            'status'    => InvoiceStatus::ISSUED->value,
            'issued_at' => now()->subHour(),
        ]);
    }

    public function paid(): static
    {
        return $this->state([
            'status'    => InvoiceStatus::PAID->value,
            'issued_at' => now()->subDay(),
        ]);
    }

    public function voided(): static
    {
        return $this->state(['status' => InvoiceStatus::VOIDED->value]);
    }
}
