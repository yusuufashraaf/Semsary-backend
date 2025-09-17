<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PropertyList;

class BedroomsSeeder extends Seeder
{
    public function run()
    {
        $properties = PropertyList::all();

        foreach ($properties as $property) {
            // Option 1: Use bedrooms from location JSON if exists
            $bedrooms = $property->location['bedrooms'] ?? rand(1, 5); // fallback random 1-5

            $property->bedrooms = $bedrooms;
            $property->save();
        }

        $this->command->info('Bedrooms column updated for all properties.');
    }
}