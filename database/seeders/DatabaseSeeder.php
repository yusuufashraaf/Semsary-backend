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
        UserSeeder::class,    // Run first to create owners
        PropertySeeder::class, // Then properties
        ReviewSeeder::class,   // Then reviews
        NotificationSeeder::class, // Notifications
        BookingSeeder::class,  // Finally bookings
        PurchaseSeeder::class,
        AdminUserManagementSeeder::class,
        // ... other seeders like ReviewSeeder
    ]);
        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
    }
}
