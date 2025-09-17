<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\AdminAction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserManagementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin user if not exists
        $admin = User::firstOrCreate(
            ['email' => 'admin@semsary.com'],
            [
                'first_name' => 'Admin',
                'last_name' => 'User',
                'password' => Hash::make('admin123456'),
                'role' => 'admin',
                'status' => 'active',
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
                'phone_number' => '+201234567890',
            ]
        );

        // Create test users with different statuses
        $testUsers = [
            [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john.doe@example.com',
                'phone_number' => '+201111111111',
                'role' => 'owner',
                'status' => 'active',
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
            ],
            [
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'email' => 'jane.smith@example.com',
                'phone_number' => '+201222222222',
                'role' => 'agent',
                'status' => 'pending',
                'email_verified_at' => now(),
                'phone_verified_at' => null,
            ],
            [
                'first_name' => 'Bob',
                'last_name' => 'Johnson',
                'email' => 'bob.johnson@example.com',
                'phone_number' => '+201333333333',
                'role' => 'user',
                'status' => 'suspended',
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
            ],
            [
                'first_name' => 'Alice',
                'last_name' => 'Williams',
                'email' => 'alice.williams@example.com',
                'phone_number' => '+201444444444',
                'role' => 'user',
                'status' => 'pending',
                'email_verified_at' => null,
                'phone_verified_at' => null,
            ],
            [
                'first_name' => 'Charlie',
                'last_name' => 'Brown',
                'email' => 'charlie.brown@example.com',
                'phone_number' => '+201555555555',
                'role' => 'owner',
                'status' => 'active',
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
            ],
        ];

        foreach ($testUsers as $userData) {
            $userData['password'] = Hash::make('password123');

            $user = User::create($userData);

            // Create admin actions for some users
            if ($user->status === 'suspended') {
                AdminAction::log(
                    $admin->id,
                    $user->id,
                    'suspend',
                    'User suspended for violating community guidelines'
                );
            }

            if ($user->status === 'pending') {
                AdminAction::log(
                    $admin->id,
                    $user->id,
                    'pending',
                    'User waiting for verification'
                );
            }

            if ($user->status === 'active' && $user->role === 'owner') {
                AdminAction::log(
                    $admin->id,
                    $user->id,
                    'activate',
                    'User account verified'
                );
            }
        }

        $this->command->info('Simple admin user management seeded successfully!');
        $this->command->info('Admin login: admin@semsary.com / admin123456');
        $this->command->info('Test users created with sample admin actions');
    }
}
