<?php

namespace Database\Factories;

use App\Enums\UserRole as UserRoleEnum;
use App\Enums\UserStatus;
use App\Models\UserProfile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id'                  => Str::uuid(),
            'username'            => fake()->unique()->userName(),
            'email'               => fake()->unique()->safeEmail(),
            'password'            => bcrypt('password'),
            'status'              => UserStatus::ACTIVE,
            'failed_login_count'  => 0,
            'locked_until'        => null,
            'version'             => 1,
            'remember_token'      => Str::random(10),
        ];
    }

    /** Create with a UserProfile attached. */
    public function withProfile(): static
    {
        return $this->afterCreating(function ($user) {
            UserProfile::factory()->create(['user_id' => $user->id]);
        });
    }

    /** Create with the given roles assigned. */
    public function withRoles(UserRoleEnum ...$roles): static
    {
        return $this->afterCreating(function ($user) use ($roles) {
            foreach ($roles as $role) {
                $user->roles()->create([
                    'role'        => $role->value,
                    'assigned_at' => now(),
                ]);
            }
        });
    }

    public function suspended(): static
    {
        return $this->state(['status' => UserStatus::SUSPENDED]);
    }

    public function deactivated(): static
    {
        return $this->state(['status' => UserStatus::DEACTIVATED]);
    }

    public function pending(): static
    {
        return $this->state(['status' => UserStatus::PENDING]);
    }
}
