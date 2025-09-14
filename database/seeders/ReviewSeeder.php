<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Faker\Factory as Faker;

class ReviewSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Temporarily disable foreign key constraints
        Schema::disableForeignKeyConstraints();
        DB::table('reviews')->truncate();
        Schema::enableForeignKeyConstraints();

        $faker = Faker::create();

        // Create 20 random reviews
        for ($i = 0; $i < 20; $i++) {
            DB::table('reviews')->insert([
                'property_id' => $faker->numberBetween(1, 10), // Assuming you have properties with IDs 1-10
                'user_id' => $faker->numberBetween(1, 5),     // Assuming you have users with IDs 1-5
                'comment' => $faker->paragraph(2),
                'rating' => $faker->numberBetween(1, 5),
                'created_at' => $faker->dateTimeBetween('-1 year', 'now'),
                'updated_at' => now(),
            ]);
        }
    }
}