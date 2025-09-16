<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PropertyList;

class BathroomSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all properties
        $properties = PropertyList::all();

        foreach ($properties as $property) {
            // Randomly assign between 1 and 5 bathrooms
            $property->bathrooms = rand(1, 5);
            $property->save();

            $this->command->info("Property ID {$property->id} bathrooms set to {$property->bathrooms}");
        }

        $this->command->info('Bathrooms have been populated for all properties!');
    }
}