<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Faker\Factory as Faker;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('users')->truncate();
        Schema::enableForeignKeyConstraints();

        $faker = Faker::create();

        // Predefined roles and statuses
        $roles = ['user', 'owner', 'agent', 'admin'];
        $statuses = ['active', 'pending', 'suspended'];

        // Create specific users first
        $specificUsers = [
            [
                'first_name' => 'Admin',
                'last_name' => 'User',
                'email' => 'admin@example.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password123'),
                'role' => 'admin',
                'phone_number' => $faker->phoneNumber(),
                'status' => 'active',
                'phone_verified_at' => now(),
                'remember_token' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'first_name' => 'Property',
                'last_name' => 'Owner',
                'email' => 'owner@example.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password123'),
                'role' => 'owner',
                'phone_number' => $faker->phoneNumber(),
                'status' => 'active',
                'phone_verified_at' => now(),
                'remember_token' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'first_name' => 'Real Estate',
                'last_name' => 'Agent',
                'email' => 'agent@example.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password123'),
                'role' => 'agent',
                'phone_number' => $faker->phoneNumber(),
                'status' => 'active',
                'phone_verified_at' => now(),
                'remember_token' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ];

        DB::table('users')->insert($specificUsers);

        // Create 20 random users
        for ($i = 0; $i < 20; $i++) {
            $status = $faker->randomElement($statuses);
            $emailVerified = $faker->optional(0.8)->dateTimeThisYear(); // 80% chance of being verified
            $phoneVerified = ($status === 'active') ? $faker->optional(0.7)->dateTimeThisYear() : null;

            DB::table('users')->insert([
                'first_name' => $faker->firstName(),
                'last_name' => $faker->lastName(),
                'email' => $faker->unique()->safeEmail(),
                'email_verified_at' => $emailVerified,
                'password' => Hash::make('password123'),
                'role' => $faker->randomElement($roles),
                'phone_number' => $faker->phoneNumber(),
                'status' => $status,
                'phone_verified_at' => $phoneVerified,
                'remember_token' => \Illuminate\Support\Str::random(10),
                'created_at' => $faker->dateTimeThisYear(),
                'updated_at' => now(),
            ]);
        }
    }
}