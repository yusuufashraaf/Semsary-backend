<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\PropertyPurchase;
use App\Models\User;
use App\Models\Property;
use Carbon\Carbon;

class PropertyPurchaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get buyers and sellers
        $buyers = User::where('role', 'user')->get();
        $sellers = User::where('role', 'owner')->get();
        $properties = Property::where('price_type', 'FullPay')->get();

        if ($buyers->isEmpty() || $sellers->isEmpty() || $properties->isEmpty()) {
            $this->command->warn('No buyers, sellers, or FullPay properties found. Please run User and Property seeders first.');
            return;
        }

        $statuses = ['pending', 'paid', 'completed', 'cancelled', 'refunded'];
        $paymentGateways = ['PayMob', 'PayPal', 'Fawry', 'Stripe'];

        foreach ($buyers as $buyer) {
            // Create 1-3 property purchases per buyer
            $purchaseCount = rand(1, 3);
            
            for ($i = 0; $i < $purchaseCount; $i++) {
                $property = $properties->random();
                $seller = $sellers->where('id', $property->owner_id)->first() ?? $sellers->random();
                
                $status = $statuses[array_rand($statuses)];
                
                $purchaseDate = now()->subDays(rand(0, 90));
                $cancellationDeadline = $status === 'pending' ? $purchaseDate->copy()->addDays(7) : null;
                $completionDate = $status === 'completed' ? $purchaseDate->copy()->addDays(rand(1, 30)) : null;
                $cancelledAt = $status === 'cancelled' ? $purchaseDate->copy()->addDays(rand(1, 6)) : null;

                PropertyPurchase::create([
                    'property_id' => $property->id,
                    'buyer_id' => $buyer->id,
                    'seller_id' => $seller->id,
                    'amount' => $property->price,
                    'status' => $status,
                    'payment_gateway' => $paymentGateways[array_rand($paymentGateways)],
                    'transaction_ref' => 'PP_' . uniqid() . '_' . rand(1000, 9999),
                    'idempotency_key' => 'idemp_' . uniqid(),
                    'purchase_date' => $purchaseDate,
                    'cancellation_deadline' => $cancellationDeadline,
                    'completion_date' => $completionDate,
                    'cancelled_at' => $cancelledAt,
                    'metadata' => $this->generateMetadata($status, $property, $buyer),
                    'created_at' => $purchaseDate,
                    'updated_at' => $purchaseDate,
                ]);
            }
        }

        $this->command->info('Property purchases seeded successfully!');
    }

    /**
     * Generate metadata based on status
     */
    private function generateMetadata($status, $property, $buyer): array
    {
        $metadata = [
            'property_title' => $property->title,
            'buyer_name' => $buyer->first_name . ' ' . $buyer->last_name,
            'property_type' => $property->type,
            'location' => $property->location_string,
        ];

        if ($status === 'paid') {
            $metadata['payment_confirmed_at'] = now()->toDateTimeString();
            $metadata['escrow_activated'] = true;
        } elseif ($status === 'completed') {
            $metadata['property_transferred'] = true;
            $metadata['ownership_transferred_at'] = now()->toDateTimeString();
        } elseif ($status === 'cancelled') {
            $metadata['cancellation_reason'] = 'Buyer request';
            $metadata['refund_initiated'] = true;
        } elseif ($status === 'refunded') {
            $metadata['refund_completed_at'] = now()->toDateTimeString();
            $metadata['refund_amount'] = $property->price;
        }

        return $metadata;
    }
}