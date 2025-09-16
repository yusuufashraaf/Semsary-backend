<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Faker\Factory as Faker;

class PropertySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Temporarily disable foreign key constraints
        Schema::disableForeignKeyConstraints();
        DB::table('properties')->truncate();
        Schema::enableForeignKeyConstraints();

        $faker = Faker::create();

        // Predefined property types, price types, and states
        $propertyTypes = ['Apartment', 'Villa', 'Duplex', 'Roof', 'Land'];
        $priceTypes = ['FullPay', 'Monthly', 'Daily'];
        $propertyStates = ['Valid', 'Invalid', 'Pending', 'Rented', 'Sold'];

        // Sample locations (cities with coordinates)
        $locations = [
            ['city' => 'New York', 'lat' => 40.7128, 'lng' => -74.0060],
            ['city' => 'Los Angeles', 'lat' => 34.0522, 'lng' => -118.2437],
            ['city' => 'Chicago', 'lat' => 41.8781, 'lng' => -87.6298],
            ['city' => 'Miami', 'lat' => 25.7617, 'lng' => -80.1918],
            ['city' => 'San Francisco', 'lat' => 37.7749, 'lng' => -122.4194],
            ['city' => 'Seattle', 'lat' => 47.6062, 'lng' => -122.3321],
            ['city' => 'Austin', 'lat' => 30.2672, 'lng' => -97.7431],
            ['city' => 'Boston', 'lat' => 42.3601, 'lng' => -71.0589],
        ];

        // Create 25 properties
        for ($i = 0; $i < 25; $i++) {
            $propertyType = $faker->randomElement($propertyTypes);
            $priceType = $faker->randomElement($priceTypes);
            $propertyState = $faker->randomElement($propertyStates);
            $location = $faker->randomElement($locations);
            
            // Generate appropriate price based on type and price type
            $price = $this->generatePrice($propertyType, $priceType, $faker);
            
            // Generate appropriate size based on property type
            $size = $this->generateSize($propertyType, $faker);

            DB::table('properties')->insert([
                'owner_id' => $faker->numberBetween(2, 10), // Assuming owners have IDs 2-10
                'title' => $this->generateTitle($propertyType, $location['city'], $faker),
                'description' => $this->generateDescription($propertyType, $location['city'], $faker),
                'type' => $propertyType,
                'price' => $price,
                'price_type' => $priceType,
                'location' => json_encode([
                    'city' => $location['city'],
                    'state' => $faker->stateAbbr(),
                    'zip_code' => $faker->postcode(),
                    'address' => $faker->streetAddress(),
                    'latitude' => $location['lat'] + $faker->randomFloat(6, -0.1, 0.1),
                    'longitude' => $location['lng'] + $faker->randomFloat(6, -0.1, 0.1),
                ]),
                'size' => $size,
                'property_state' => $propertyState,
                'created_at' => $faker->dateTimeBetween('-1 year', 'now'),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Generate appropriate price based on property type and price type
     */
    private function generatePrice(string $propertyType, string $priceType, $faker): float
    {
        $basePrice = match($propertyType) {
            'Apartment' => $faker->numberBetween(150000, 800000),
            'Villa' => $faker->numberBetween(500000, 2000000),
            'Duplex' => $faker->numberBetween(300000, 1200000),
            'Roof' => $faker->numberBetween(200000, 800000),
            'Land' => $faker->numberBetween(50000, 500000),
        };

        return match($priceType) {
            'Monthly' => $basePrice / 12, // Convert annual to monthly
            'Daily' => $basePrice / 365,  // Convert annual to daily
            default => $basePrice,        // FullPay remains as is
        };
    }

    /**
     * Generate appropriate size based on property type
     */
    private function generateSize(string $propertyType, $faker): int
    {
        return match($propertyType) {
            'Apartment' => $faker->numberBetween(500, 2500),
            'Villa' => $faker->numberBetween(2000, 8000),
            'Duplex' => $faker->numberBetween(1500, 4000),
            'Roof' => $faker->numberBetween(800, 2000),
            'Land' => $faker->numberBetween(1000, 10000),
        };
    }

    /**
     * Generate property title
     */
    private function generateTitle(string $propertyType, string $city, $faker): string
    {
        $adjectives = ['Luxury', 'Modern', 'Spacious', 'Beautiful', 'Elegant', 'Contemporary', 'Charming'];
        $features = ['with Pool', 'City View', 'Garden', 'Parking', 'Renovated', 'Furnished'];

        return $faker->randomElement($adjectives) . " " . $propertyType . 
               " in " . $city . " " . $faker->optional(0.6)->randomElement($features);
    }

    /**
     * Generate property description
     */
    private function generateDescription(string $propertyType, string $city, $faker): string
    {
        $descriptions = [
            'Apartment' => "Beautiful {$propertyType} located in the heart of {$city}. Features modern amenities, spacious rooms, and stunning views. Perfect for urban living with easy access to transportation, shopping, and dining.",
            'Villa' => "Luxurious {$propertyType} in prestigious {$city} neighborhood. Offers privacy, elegance, and exceptional quality. Includes premium finishes, landscaped gardens, and premium amenities.",
            'Duplex' => "Spacious {$propertyType} in desirable {$city} area. Ideal for families seeking comfort and style. Features multiple levels, modern kitchen, and outdoor space.",
            'Roof' => "Unique {$propertyType} apartment with panoramic views of {$city}. Modern design with open concept living, perfect for entertaining and enjoying city life.",
            'Land' => "Prime {$propertyType} opportunity in growing {$city} area. Excellent investment potential with various development possibilities. Utilities available and ready for construction."
        ];

        return $descriptions[$propertyType] . " " . $faker->paragraph(2);
    }
}