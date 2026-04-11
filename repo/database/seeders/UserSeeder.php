<?php

namespace Database\Seeders;

use App\Enums\CredentialingStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Doctor;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /** Default password for all seeded accounts: Seed1234!@ */
    private const DEFAULT_PASSWORD = 'Seed1234!@';

    private array $demoUsers = [
        [
            'username' => 'admin',
            'email'    => 'admin@medvoyage.test',
            'first'    => 'Admin',
            'last'     => 'User',
            'roles'    => [UserRole::ADMIN],
        ],
        [
            'username' => 'reviewer',
            'email'    => 'reviewer@medvoyage.test',
            'first'    => 'Carol',
            'last'     => 'Reviewer',
            'roles'    => [UserRole::CREDENTIALING_REVIEWER],
        ],
        [
            'username' => 'finance',
            'email'    => 'finance@medvoyage.test',
            'first'    => 'Frank',
            'last'     => 'Finance',
            'roles'    => [UserRole::FINANCE_SPECIALIST],
        ],
        [
            'username' => 'doctor',
            'email'    => 'doctor@medvoyage.test',
            'first'    => 'Dr. James',
            'last'     => 'Wilson',
            'roles'    => [UserRole::DOCTOR, UserRole::MEMBER],
        ],
        [
            'username' => 'member',
            'email'    => 'member@medvoyage.test',
            'first'    => 'Alice',
            'last'     => 'Member',
            'roles'    => [UserRole::MEMBER],
        ],
    ];

    public function run(): void
    {
        foreach ($this->demoUsers as $data) {
            $user = User::create([
                'username' => $data['username'],
                'email'    => $data['email'],
                'password' => bcrypt(self::DEFAULT_PASSWORD),
                'status'   => UserStatus::ACTIVE,
                'version'  => 1,
            ]);

            UserProfile::create([
                'user_id'    => $user->id,
                'first_name' => $data['first'],
                'last_name'  => $data['last'],
            ]);

            foreach ($data['roles'] as $role) {
                $user->addRole($role);
            }

            // Doctors need a Doctor profile row so /credentialing/profile works.
            if (in_array(UserRole::DOCTOR, $data['roles'], true)) {
                Doctor::create([
                    'user_id'              => $user->id,
                    'specialty'            => 'Internal Medicine',
                    'npi_number'           => '1234567890',
                    'license_state'        => 'CA',
                    'license_expiry'       => now()->addYears(2)->toDateString(),
                    'credentialing_status' => CredentialingStatus::APPROVED,
                    'activated_at'         => now()->subDays(30),
                    'version'              => 1,
                ]);
            }
        }
    }
}
