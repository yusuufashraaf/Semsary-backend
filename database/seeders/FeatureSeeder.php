<?php

namespace Database\Seeders;

use App\Models\Feature;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FeatureSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $features = [
            'Swimming Pool',
            'Gym',
            'Garden',
            'Garage',
            'Fireplace',
            'Air Conditioning',
            'Balcony',
            'Security System',
            'Hardwood Floors',
            'Stainless Steel Appliances'
        ];

        foreach($features as $feature){
            Feature::create(['name' => $feature]);
        }
    }
}
