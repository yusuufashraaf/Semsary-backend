<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class PropertyImageSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create();

        // Get all property IDs
        $propertyIds = DB::table('properties')->pluck('id')->toArray();

        // Sample image URLs (you can replace with your own)
        $sampleImages = [
            'https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?w=800',
            'https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?w=800',
            'https://images.unsplash.com/photo-1600047509807-ba8f99d2cdde?w=800',
            'https://images.unsplash.com/photo-1600047509358-9dc75507daeb?w=800',
            'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=800',
        ];

        foreach ($propertyIds as $propertyId) {
            // Each property gets 3–5 images
            $numImages = rand(3, 5);

            for ($i = 0; $i < $numImages; $i++) {
                $imageUrl = $faker->randomElement($sampleImages);

                DB::table('property_images')->insert([
                    'property_id' => $propertyId,
                    'image_url' => $imageUrl,
                    'order_index' => $i,
                    'description' => $faker->sentence(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}