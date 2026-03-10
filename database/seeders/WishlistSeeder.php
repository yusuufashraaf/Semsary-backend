<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Wishlist;
use App\Models\User;
use App\Models\Property;

class WishlistSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing records to start fresh
        Wishlist::truncate();
        
        // Get all users and properties
        $users = User::all();
        $properties = Property::all();
        
        // Check if we have users and properties
        if ($users->isEmpty() || $properties->isEmpty()) {
            $this->command->info('No users or properties found. Please run User and Property seeders first.');
            return;
        }
        
        // Create wishlist items
        $wishlistItems = [];
        
        // For each user, add 3-5 random properties to their wishlist
        foreach ($users as $user) {
            // Get random properties (between 3-5 per user)
            $randomProperties = $properties->random(rand(3, 5));
            
            foreach ($randomProperties as $property) {
                $wishlistItems[] = [
                    'user_id' => $user->id,
                    'property_id' => $property->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }
        
        // Insert all wishlist items
        Wishlist::insert($wishlistItems);
        
        $this->command->info('Wishlist seeded successfully!');
        $this->command->info('Created ' . count($wishlistItems) . ' wishlist items.');
    }
}