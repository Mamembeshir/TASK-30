<?php

namespace Database\Seeders;

use App\Enums\MembershipTier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MembershipPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name'            => 'Basic Membership',
                'description'     => 'Access to standard medical trips and community features.',
                'price_cents'     => 9900,   // $99
                'duration_months' => 12,
                'tier'            => MembershipTier::BASIC->value,
            ],
            [
                'name'            => 'Standard Membership',
                'description'     => 'Priority access to trips, discounted rates, and extended support.',
                'price_cents'     => 19900,  // $199
                'duration_months' => 12,
                'tier'            => MembershipTier::STANDARD->value,
            ],
            [
                'name'            => 'Premium Membership',
                'description'     => 'All benefits plus VIP trip access, personal coordinator, and full discounts.',
                'price_cents'     => 39900,  // $399
                'duration_months' => 12,
                'tier'            => MembershipTier::PREMIUM->value,
            ],
        ];

        foreach ($plans as $plan) {
            \DB::table('membership_plans')->insert([
                'id'              => Str::uuid(),
                'name'            => $plan['name'],
                'description'     => $plan['description'],
                'price_cents'     => $plan['price_cents'],
                'duration_months' => $plan['duration_months'],
                'tier'            => $plan['tier'],
                'is_active'       => true,
                'version'         => 1,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }
    }
}
