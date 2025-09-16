<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class PropertyFeatureSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create();

        $propertyIds = DB::table('properties')->pluck('id')->toArray();
        $featureIds = DB::table('features')->pluck('id')->toArray();

        foreach ($propertyIds as $propertyId) {
            $randomFeatures = $faker->randomElements($featureIds, rand(2, 5));

            foreach ($randomFeatures as $featureId) {
                DB::table('property_features')->insert([
                    'property_id' => $propertyId,
                    'feature_id' => $featureId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}