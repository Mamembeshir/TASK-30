<?php

namespace Database\Factories;

use App\Enums\MembershipTier;
use App\Models\MembershipPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

class MembershipPlanFactory extends Factory
{
    protected $model = MembershipPlan::class;

    public function definition(): array
    {
        $tier = $this->faker->randomElement(MembershipTier::cases());

        return [
            'name'             => $tier->label() . ' Membership',
            'description'      => $this->faker->optional()->sentence(),
            'price_cents'      => match($tier) {
                MembershipTier::BASIC    => 4900,
                MembershipTier::STANDARD => 9900,
                MembershipTier::PREMIUM  => 19900,
            },
            'duration_months'  => 12,
            'tier'             => $tier->value,
            'is_active'        => true,
            'version'          => 1,
        ];
    }

    public function basic(): static
    {
        return $this->state([
            'tier'        => MembershipTier::BASIC->value,
            'name'        => 'Basic Membership',
            'price_cents' => 4900,
        ]);
    }

    public function standard(): static
    {
        return $this->state([
            'tier'        => MembershipTier::STANDARD->value,
            'name'        => 'Standard Membership',
            'price_cents' => 9900,
        ]);
    }

    public function premium(): static
    {
        return $this->state([
            'tier'        => MembershipTier::PREMIUM->value,
            'name'        => 'Premium Membership',
            'price_cents' => 19900,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
