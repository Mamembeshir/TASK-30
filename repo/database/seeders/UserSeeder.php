<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
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
                'id'       => Str::uuid(),
                'username' => $data['username'],
                'email'    => $data['email'],
                'password' => bcrypt('password'),
                'status'   => UserStatus::ACTIVE,
                'version'  => 1,
            ]);

            UserProfile::create([
                'user_id'    => $user->id,
                'first_name' => $data['first'],
                'last_name'  => $data['last'],
            ]);

            foreach ($data['roles'] as $role) {
                \DB::table('user_roles')->insert([
                    'user_id'     => $user->id,
                    'role'        => $role->value,
                    'assigned_at' => now(),
                ]);
            }
        }
    }
}
