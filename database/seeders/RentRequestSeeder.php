<?php

namespace Database\Seeders;

use App\Models\RentRequest;
use App\Models\User;
use App\Models\Property;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class RentRequestSeeder extends Seeder
{
    public function run(): void
    {
        // Get users and properties
        $users = User::where('role', 'user')->take(10)->get();
        $properties = Property::where('status', 'rent')->take(15)->get();

        if ($users->isEmpty() || $properties->isEmpty()) {
            $this->command->warn('No users or rent properties found. Please run User and Property seeders first.');
            return;
        }

        $statuses = ['pending', 'cancelled', 'rejected', 'confirmed', 'cancelled_by_owner', 'paid', 'completed'];

        foreach ($users as $user) {
            // Create 2-5 rent requests per user
            $requestCount = rand(2, 5);
            
            for ($i = 0; $i < $requestCount; $i++) {
                $property = $properties->random();
                
                // Generate random dates (within next 6 months)
                $checkIn = Carbon::now()->addDays(rand(5, 60));
                $checkOut = $checkIn->copy()->addDays(rand(2, 14));
                
                $status = $statuses[array_rand($statuses)];
                
                RentRequest::create([
                    'user_id' => $user->id,
                    'property_id' => $property->id,
                    'check_in' => $checkIn,
                    'check_out' => $checkOut,
                    'status' => $status,
                    'blocked_until' => $this->getBlockedUntil($status),
                    'payment_deadline' => $this->getPaymentDeadline($status, $checkIn),
                    'cooldown_expires_at' => $this->getCooldownExpiresAt($status),
                    'created_at' => Carbon::now()->subDays(rand(1, 30)),
                    'updated_at' => Carbon::now(),
                ]);
            }
        }

        $this->command->info('Rent requests seeded successfully!');
    }

    private function getBlockedUntil(?string $status): ?Carbon
    {
        return match($status) {
            'paid', 'completed' => Carbon::now()->addDays(rand(30, 365)),
            'confirmed' => Carbon::now()->addHours(24),
            default => null,
        };
    }

    private function getPaymentDeadline(?string $status, Carbon $checkIn): ?Carbon
    {
        if (in_array($status, ['confirmed', 'paid', 'completed'])) {
            // Payment deadline is 48 hours before check-in or random past date for completed
            return $status === 'completed' 
                ? Carbon::now()->subDays(rand(1, 30))
                : $checkIn->copy()->subHours(48);
        }
        
        return null;
    }

    private function getCooldownExpiresAt(?string $status): ?Carbon
    {
        return match($status) {
            'cancelled', 'rejected', 'cancelled_by_owner' => Carbon::now()->addHours(rand(24, 72)),
            default => null,
        };
    }
}