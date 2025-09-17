<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Faker\Factory as Faker;
use App\Models\Property;
use App\Models\Transaction;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('users')->truncate();
        DB::table('properties')->truncate();
        DB::table('transactions')->truncate();
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
                'email' => 'admin@semsary.com',
                'email_verified_at' => now(),
                'password' => Hash::make('admin123'),
                'role' => 'admin',
                'phone_number' => '+201000000000',
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

        // Create random users with different roles
        $regularUsers = [];
        $owners = [];
        $agents = [];

        // Create 20 regular users
        for ($i = 0; $i < 20; $i++) {
            $status = $faker->randomElement($statuses);
            $role = $faker->randomElement(['user', 'owner', 'agent']);
            $emailVerified = $faker->optional(0.8)->dateTimeThisYear();
            $phoneVerified = ($status === 'active') ? $faker->optional(0.7)->dateTimeThisYear() : null;

            $user = User::create([
                'first_name' => $faker->firstName(),
                'last_name' => $faker->lastName(),
                'email' => $faker->unique()->safeEmail(),
                'email_verified_at' => $emailVerified,
                'password' => Hash::make('password123'),
                'role' => $role,
                'phone_number' => $faker->phoneNumber(),
                'status' => $status,
                'phone_verified_at' => $phoneVerified,
                'remember_token' => \Illuminate\Support\Str::random(10),
                'created_at' => $faker->dateTimeThisYear(),
                'updated_at' => now(),
            ]);

            if ($role === 'user') {
                $regularUsers[] = $user;
            } elseif ($role === 'owner') {
                $owners[] = $user;
            } elseif ($role === 'agent') {
                $agents[] = $user;
            }
        }

        // Create properties for owners
        $allProperties = [];
        foreach ($owners as $owner) {
            $propertyCount = rand(1, 3);
            for ($j = 0; $j < $propertyCount; $j++) {
                $property = Property::create([
                    'owner_id' => $owner->id,
                    'title' => $faker->sentence(3),
                    'description' => $faker->paragraph(),
                    'bedrooms' => rand(1, 5),
                    'bathrooms' => rand(1, 3),
                    'type' => $faker->randomElement(['Apartment', 'Villa', 'Duplex', 'Roof', 'Land']),
                    'price' => $faker->randomFloat(2, 50000, 500000),
                    'price_type' => $faker->randomElement(['FullPay', 'Monthly', 'Daily']),
                    'location' => [
                        'address' => $faker->address,
                        'lat' => $faker->latitude(),
                        'lng' => $faker->longitude(),
                    ],
                    'size' => rand(50, 500),
                    'property_state' => $faker->randomElement(['Valid', 'Pending', 'Rented', 'Sold']),
                    'created_at' => $faker->dateTimeThisYear(),
                    'updated_at' => now(),
                ]);
                $allProperties[] = $property;
            }
        }

        // Create transactions
        $allUsers = array_merge($regularUsers, $owners, $agents);
        foreach (array_slice($allUsers, 0, 15) as $user) {
            if (!empty($allProperties)) {
                $transactionCount = rand(1, 3);
                for ($k = 0; $k < $transactionCount; $k++) {
                    Transaction::create([
                        'user_id' => $user->id,
                        'property_id' => $faker->randomElement($allProperties)->id,
                        'type' => $faker->randomElement(['rent', 'buy']),
                        'status' => $faker->randomElement(['success', 'pending', 'failed']),
                        'amount' => $faker->randomFloat(2, 500, 5000),
                        'deposit_amount' => $faker->randomFloat(2, 50, 500),
                        'payment_gateway' => $faker->randomElement(['PayMob', 'PayPal', 'Fawry']),
                        'created_at' => $faker->dateTimeThisYear(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        $this->command->info('Users, properties, and transactions created successfully!');
        $this->command->info('Admin credentials: admin@semsary.com / admin123');
    }
}
