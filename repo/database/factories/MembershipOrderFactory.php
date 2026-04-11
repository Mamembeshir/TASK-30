<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\MembershipOrder;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MembershipOrderFactory extends Factory
{
    protected $model = MembershipOrder::class;

    public function definition(): array
    {
        $now = now();

        return [
            'user_id'               => User::factory(),
            'plan_id'               => MembershipPlan::factory(),
            'order_type'            => OrderType::PURCHASE->value,
            'amount_cents'          => 9900,
            'status'                => OrderStatus::PENDING->value,
            'starts_at'             => $now,
            'expires_at'            => $now->copy()->addYear(),
            'top_up_eligible_until' => $now->copy()->addDays(30),
            'idempotency_key'       => (string) Str::uuid(),
            'version'               => 1,
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => OrderStatus::PENDING->value]);
    }

    public function paid(): static
    {
        return $this->state(function () {
            return [
                'status'     => OrderStatus::PAID->value,
                'payment_id' => Payment::factory(),
            ];
        });
    }

    public function active(): static
    {
        return $this->state(function () {
            $now = now();
            return [
                'status'                => OrderStatus::PAID->value,
                'payment_id'            => Payment::factory(),
                'starts_at'             => $now,
                'expires_at'            => $now->copy()->addYear(),
                'top_up_eligible_until' => $now->copy()->addDays(30),
            ];
        });
    }

    public function expired(): static
    {
        return $this->state(function () {
            $past = now()->subYear();
            return [
                'status'                => OrderStatus::PAID->value,
                'payment_id'            => Payment::factory(),
                'starts_at'             => $past->copy()->subYear(),
                'expires_at'            => $past,
                'top_up_eligible_until' => null,
            ];
        });
    }

    public function refunded(): static
    {
        return $this->state([
            'status'     => OrderStatus::REFUNDED->value,
            'expires_at' => now(),
        ]);
    }
}
