<?php

namespace Database\Seeders;

use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PropertySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all user IDs to use as owners
        $userIds = User::pluck('id')->toArray();

        if (empty($userIds)) {
            // Create a test user if no users exist
            $userId = DB::table('users')->insertGetId([
                'name' => 'Property Owner',
                'email' => 'owner@example.com',
                'password' => bcrypt('password'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $userIds = [$userId];
        }

        $properties = [
            [
                'owner_id' => $userIds[array_rand($userIds)],
                'title' => 'Luxury Apartment in Downtown',
                'description' => 'Beautiful luxury apartment with stunning city views. Modern amenities and spacious rooms.',
                'bedrooms' => 3,
                'bathrooms' => 2,
                'type' => 'Apartment',
                'price' => 250000.00,
                'price_type' => 'FullPay',
                'location' => [
                    'city' => 'New York',
                    'state' => 'NY',
                    'zip_code' => '10001',
                    'address' => '123 Main Street',
                    'latitude' => 40.7128,
                    'longitude' => -74.0060
                ],
                'size' => 1200,
                'property_state' => 'Valid',
            ],
            [
                'owner_id' => $userIds[array_rand($userIds)],
                'title' => 'Cozy Villa with Pool',
                'description' => 'Beautiful villa with private pool and garden. Perfect for family vacations.',
                'bedrooms' => 4,
                'bathrooms' => 3,
                'type' => 'Villa',
                'price' => 4500.00,
                'price_type' => 'Monthly',
                'location' => [
                    'city' => 'Los Angeles',
                    'state' => 'CA',
                    'zip_code' => '90001',
                    'address' => '456 Oak Avenue',
                    'latitude' => 34.0522,
                    'longitude' => -118.2437
                ],
                'size' => 2200,
                'property_state' => 'Rented',
            ],
            [
                'owner_id' => $userIds[array_rand($userIds)],
                'title' => 'Modern Duplex Unit',
                'description' => 'Spacious duplex with two floors and private entrance. Modern design and finishes.',
                'bedrooms' => 3,
                'bathrooms' => 2,
                'type' => 'Duplex',
                'price' => 120.00,
                'price_type' => 'Daily',
                'location' => [
                    'city' => 'Chicago',
                    'state' => 'IL',
                    'zip_code' => '60601',
                    'address' => '789 Pine Street',
                    'latitude' => 41.8781,
                    'longitude' => -87.6298
                ],
                'size' => 1800,
                'property_state' => 'Pending',
            ],
            [
                'owner_id' => $userIds[array_rand($userIds)],
                'title' => 'Elegant Roof Apartment',
                'description' => 'Unique roof apartment with panoramic city views. Perfect for entertaining.',
                'bedrooms' => 2,
                'bathrooms' => 1,
                'type' => 'Roof',
                'price' => 1800.00,
                'price_type' => 'Monthly',
                'location' => [
                    'city' => 'Miami',
                    'state' => 'FL',
                    'zip_code' => '33101',
                    'address' => '101 Beach Boulevard',
                    'latitude' => 25.7617,
                    'longitude' => -80.1918
                ],
                'size' => 950,
                'property_state' => 'Valid',
            ],
            [
                'owner_id' => $userIds[array_rand($userIds)],
                'title' => 'Vacant Land for Development',
                'description' => 'Prime location land ready for construction. Zoned for residential use.',
                'bedrooms' => 0,
                'bathrooms' => 0,
                'type' => 'Land',
                'price' => 150000.00,
                'price_type' => 'FullPay',
                'location' => [
                    'city' => 'Austin',
                    'state' => 'TX',
                    'zip_code' => '73301',
                    'address' => '202 Development Lane',
                    'latitude' => 30.2672,
                    'longitude' => -97.7431
                ],
                'size' => 5000,
                'property_state' => 'Valid',
            ],
        ];

        foreach ($properties as $property) {
            Property::create($property);
        }

        // Create additional random properties
        $propertyTypes = ['Apartment', 'Villa', 'Duplex', 'Roof', 'Land'];
        $priceTypes = ['FullPay', 'Monthly', 'Daily'];
        $propertyStates = ['Valid', 'Invalid', 'Pending', 'Rented', 'Sold'];

        for ($i = 0; $i < 15; $i++) {
            $type = $propertyTypes[array_rand($propertyTypes)];
            
            // Adjust bedrooms and bathrooms based on property type
            if ($type === 'Land') {
                $bedrooms = 0;
                $bathrooms = 0;
            } elseif ($type === 'Roof') {
                $bedrooms = rand(1, 2);
                $bathrooms = rand(1, 2);
            } elseif ($type === 'Duplex') {
                $bedrooms = rand(2, 4);
                $bathrooms = rand(2, 3);
            } else {
                $bedrooms = rand(1, 5);
                $bathrooms = rand(1, 4);
            }

            Property::create([
                'owner_id' => $userIds[array_rand($userIds)],
                'title' => $this->generatePropertyTitle($type),
                'description' => $this->generatePropertyDescription($type),
                'bedrooms' => $bedrooms,
                'bathrooms' => $bathrooms,
                'type' => $type,
                'price' => $this->generatePrice($type, $priceTypes[array_rand($priceTypes)]),
                'price_type' => $priceTypes[array_rand($priceTypes)],
                'location' => $this->generateLocation(),
                'size' => $this->generateSize($type),
                'property_state' => $propertyStates[array_rand($propertyStates)],
            ]);
        }

        $this->command->info('Properties seeded successfully!');
    }

    /**
     * Generate a random property title based on type.
     */
    private function generatePropertyTitle(string $type): string
    {
        $adjectives = ['Beautiful', 'Spacious', 'Modern', 'Luxury', 'Cozy', 'Elegant', 'Charming', 'Stunning'];
        $locations = ['Downtown', 'Hills', 'Beachfront', 'Garden District', 'Financial District', 'Waterfront'];

        $typeSpecific = [
            'Apartment' => ['City View', 'Downtown', 'Urban'],
            'Villa' => ['with Pool', 'Beachfront', 'Luxury'],
            'Duplex' => ['Family', 'Modern', 'Spacious'],
            'Roof' => ['Panoramic View', 'Penthouse', 'Skyline'],
            'Land' => ['Vacant', 'Development', 'Prime Location']
        ];

        return $adjectives[array_rand($adjectives)] . ' ' . 
               $type . ' ' . 
               $typeSpecific[$type][array_rand($typeSpecific[$type])] . ' in ' . 
               $locations[array_rand($locations)];
    }

    /**
     * Generate a random property description based on type.
     */
    private function generatePropertyDescription(string $type): string
    {
        $descriptions = [
            'Apartment' => [
                'Modern apartment with city views and convenient location near amenities.',
                'Spacious apartment perfect for urban living with easy access to transportation.',
                'Recently renovated apartment featuring high-quality finishes and modern appliances.'
            ],
            'Villa' => [
                'Luxury villa with private pool, garden, and premium finishes throughout.',
                'Beautiful villa offering privacy and comfort in a prime location.',
                'Spacious villa perfect for family living and entertaining guests.'
            ],
            'Duplex' => [
                'Modern duplex unit with two floors and private entrance for added privacy.',
                'Spacious duplex featuring separate living areas and modern design.',
                'Family-friendly duplex with ample space and convenient layout.'
            ],
            'Roof' => [
                'Unique roof apartment with stunning panoramic views of the city skyline.',
                'Modern roof space perfect for entertaining with outdoor living area.',
                'Elegant roof apartment featuring open concept design and premium finishes.'
            ],
            'Land' => [
                'Prime development land ready for construction in a growing area.',
                'Vacant land with excellent potential for residential or commercial development.',
                'Well-located land with easy access to utilities and transportation.'
            ]
        ];

        return $descriptions[$type][array_rand($descriptions[$type])];
    }

    /**
     * Generate appropriate price based on property type and price type.
     */
    private function generatePrice(string $type, string $priceType): float
    {
        $basePrices = [
            'Apartment' => ['FullPay' => 200000, 'Monthly' => 1500, 'Daily' => 80],
            'Villa' => ['FullPay' => 500000, 'Monthly' => 3000, 'Daily' => 150],
            'Duplex' => ['FullPay' => 350000, 'Monthly' => 2200, 'Daily' => 120],
            'Roof' => ['FullPay' => 180000, 'Monthly' => 1200, 'Daily' => 70],
            'Land' => ['FullPay' => 100000, 'Monthly' => 0, 'Daily' => 0] // Land typically not rented monthly/daily
        ];

        if ($type === 'Land' && $priceType !== 'FullPay') {
            $priceType = 'FullPay'; // Force FullPay for land
        }

        $base = $basePrices[$type][$priceType];
        $variation = $base * 0.3; // 30% variation

        return $base + (rand(-$variation, $variation));
    }

    /**
     * Generate appropriate size based on property type.
     */
    private function generateSize(string $type): int
    {
        $baseSizes = [
            'Apartment' => 800,
            'Villa' => 2000,
            'Duplex' => 1500,
            'Roof' => 600,
            'Land' => 3000
        ];

        $variation = $baseSizes[$type] * 0.4; // 40% variation

        return $baseSizes[$type] + rand(-$variation, $variation);
    }

    /**
     * Generate a random location array.
     */
    private function generateLocation(): array
    {
        $cities = [
            ['city' => 'New York', 'state' => 'NY', 'zip' => '10001'],
            ['city' => 'Los Angeles', 'state' => 'CA', 'zip' => '90001'],
            ['city' => 'Chicago', 'state' => 'IL', 'zip' => '60601'],
            ['city' => 'Houston', 'state' => 'TX', 'zip' => '77001'],
            ['city' => 'Phoenix', 'state' => 'AZ', 'zip' => '85001'],
            ['city' => 'Philadelphia', 'state' => 'PA', 'zip' => '19101'],
            ['city' => 'San Antonio', 'state' => 'TX', 'zip' => '78201'],
            ['city' => 'San Diego', 'state' => 'CA', 'zip' => '92101'],
        ];

        $city = $cities[array_rand($cities)];

        return [
            'city' => $city['city'],
            'state' => $city['state'],
            'zip_code' => $city['zip'],
            'address' => rand(100, 9999) . ' ' . 
                        ['Main', 'Oak', 'Pine', 'Maple', 'Cedar', 'Elm', 'Washington', 'Jefferson'][array_rand([0,1,2,3,4,5,6,7])] . ' ' .
                        ['Street', 'Avenue', 'Boulevard', 'Drive', 'Lane'][array_rand([0,1,2,3,4])],
            'latitude' => rand(25000000, 45000000) / 1000000,
            'longitude' => rand(-120000000, -70000000) / 1000000,
        ];
    }
}