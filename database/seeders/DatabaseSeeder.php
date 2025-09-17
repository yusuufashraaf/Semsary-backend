<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $this->call([
            UserSeeder::class,
            propertySeeder::class,
            featureSeeder::class,
            BookingSeeder::class,
            ReviewSeeder::class,
            BathroomSeeder::class,
            BedroomsSeeder::class,
            notificationSeeder::class,
            propertyFeatureSeeder::class,
            propertyImageSeeder::class,
            purchaseSeeder::class,
            AdminUserManagementSeeder::class,
            // Add other seeders as needed
        // ... other seeders like ReviewSeeder
    ]);
        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
    }
}
