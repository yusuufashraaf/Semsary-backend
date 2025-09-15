<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Booking;
use App\Models\User;
use App\Models\Property;
use Carbon\Carbon;

class BookingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing bookings
        Booking::truncate();

        $users = User::where('role', 'user')->get();
        $properties = Property::where('type', '!=', 'Land')->get();

        // Use the exact status values from your migration: pending, confirmed, cancelled
        $statuses = ['pending', 'confirmed', 'cancelled'];

        foreach ($users as $user) {
            // Create 2-4 bookings per user
            $bookingCount = rand(2, 4);
            
            for ($i = 0; $i < $bookingCount; $i++) {
                $property = $properties->random();
                
                // Generate dates - ensure end_date is after start_date
                $startDate = Carbon::now()->addDays(rand(5, 60));
                $endDate = $startDate->copy()->addDays(rand(2, 14));
                
                $days = $startDate->diffInDays($endDate);
                
                // Calculate price based on property price type
                $dailyPrice = $this->calculateDailyPrice($property);
                $totalPrice = $dailyPrice * $days;

                Booking::create([
                    'property_id' => $property->id,
                    'user_id' => $user->id,
                    'start_date' => $startDate->toDateString(), // Use date string only
                    'end_date' => $endDate->toDateString(),     // Use date string only
                    'total_price' => round($totalPrice, 2),
                    'status' => $statuses[array_rand($statuses)],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Create some past bookings (mostly confirmed)
        foreach ($users as $user) {
            $pastBookingCount = rand(1, 2);
            
            for ($i = 0; $i < $pastBookingCount; $i++) {
                $property = $properties->random();
                
                // Past dates
                $endDate = Carbon::now()->subDays(rand(1, 30));
                $startDate = $endDate->copy()->subDays(rand(2, 14));
                
                $days = $startDate->diffInDays($endDate);
                
                $dailyPrice = $this->calculateDailyPrice($property);
                $totalPrice = $dailyPrice * $days;

                Booking::create([
                    'property_id' => $property->id,
                    'user_id' => $user->id,
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                    'total_price' => round($totalPrice, 2),
                    'status' => 'confirmed', // Past bookings are mostly confirmed
                    'created_at' => $startDate->subDays(rand(1, 10)), // Created before start date
                    'updated_at' => now(),
                ]);
            }
        }

        // Create some cancelled bookings with appropriate timing
        foreach ($users as $user) {
            $cancelledCount = rand(0, 1); // Fewer cancelled bookings
            
            for ($i = 0; $i < $cancelledCount; $i++) {
                $property = $properties->random();
                
                $startDate = Carbon::now()->addDays(rand(10, 30));
                $endDate = $startDate->copy()->addDays(rand(2, 7));
                
                $days = $startDate->diffInDays($endDate);
                
                $dailyPrice = $this->calculateDailyPrice($property);
                $totalPrice = $dailyPrice * $days;

                Booking::create([
                    'property_id' => $property->id,
                    'user_id' => $user->id,
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                    'total_price' => round($totalPrice, 2),
                    'status' => 'cancelled',
                    'created_at' => now()->subDays(rand(1, 5)),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Calculate daily price based on property price type
     */
    private function calculateDailyPrice($property): float
    {
        return match($property->price_type) {
            'Monthly' => $property->price / 30,
            'Daily' => $property->price,
            'FullPay' => $property->price / 365, // For FullPay, assume annual and convert to daily
            default => $property->price / 30, // Fallback
        };
    }
}