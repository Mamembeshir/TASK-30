<?php

namespace Database\Factories;

use App\Enums\CredentialingStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Doctor>
 */
class DoctorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id'                       => Str::uuid(),
            'user_id'                  => User::factory(),
            'specialty'                => fake()->randomElement(['Cardiology', 'Emergency Medicine', 'Surgery', 'Internal Medicine']),
            'npi_number'               => fake()->numerify('##########'),
            'license_number_encrypted' => null,
            'license_number_mask'      => null,
            'license_state'            => fake()->stateAbbr(),
            'license_expiry'           => fake()->dateTimeBetween('+6 months', '+3 years')->format('Y-m-d'),
            'credentialing_status'     => CredentialingStatus::NOT_SUBMITTED,
            'activated_at'             => null,
            'version'                  => 1,
        ];
    }

    public function approved(): static
    {
        return $this->state([
            'credentialing_status' => CredentialingStatus::APPROVED,
            'activated_at'         => now()->subDays(30),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(['credentialing_status' => CredentialingStatus::REJECTED]);
    }

    public function expired(): static
    {
        return $this->state([
            'credentialing_status' => CredentialingStatus::EXPIRED,
            'license_expiry'       => now()->subDays(1)->format('Y-m-d'),
        ]);
    }

    public function withExpiredLicense(): static
    {
        return $this->state(['license_expiry' => now()->subDays(1)->format('Y-m-d')]);
    }
}
