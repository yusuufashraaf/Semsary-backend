<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Purchase;
use App\Models\User;
use App\Models\Property;

class PurchaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing purchases
        Purchase::truncate();

        $users = User::all();
        $properties = Property::all();

        $statuses = ['pending', 'success', 'failed', 'refunded'];
        $paymentGateways = ['PayMob', 'PayPal', 'Fawry', 'Stripe'];

        foreach ($users as $user) {
            // Create 1-3 purchases per user
            $purchaseCount = rand(1, 3);
            
            for ($i = 0; $i < $purchaseCount; $i++) {
                $property = $properties->random();
                
                // For purchases, we typically deal with properties that have FullPay price type
                if ($property->price_type !== 'FullPay') {
                    continue; // Skip non-FullPay properties for purchases
                }

                $status = $statuses[array_rand($statuses)];
                
                Purchase::create([
                    'user_id' => $user->id,
                    'property_id' => $property->id,
                    'status' => $status,
                    'amount' => $property->price,
                    'deposit_amount' => $status === 'pending' ? $property->price * 0.1 : null,
                    'payment_gateway' => $paymentGateways[array_rand($paymentGateways)],
                    'transaction_id' => 'txn_' . uniqid() . '_' . rand(1000, 9999),
                    'payment_details' => $this->generatePaymentDetails($status),
                    'created_at' => now()->subDays(rand(0, 90)),
                ]);
            }
        }

        // Create some successful purchases for land properties
        $landProperties = Property::where('type', 'Land')->get();
        
        foreach ($landProperties as $property) {
            $user = $users->random();
            
            Purchase::create([
                'user_id' => $user->id,
                'property_id' => $property->id,
                'status' => 'success',
                'amount' => $property->price,
                'deposit_amount' => null,
                'payment_gateway' => $paymentGateways[array_rand($paymentGateways)],
                'transaction_id' => 'txn_' . uniqid() . '_' . rand(1000, 9999),
                'payment_details' => $this->generatePaymentDetails('success'),
                'created_at' => now()->subDays(rand(10, 180)),
            ]);
        }
    }

    /**
     * Generate payment details based on status
     */
    private function generatePaymentDetails($status): array
    {
        $details = [
            'payment_method' => 'credit_card',
            'card_last_four' => rand(1000, 9999),
            'currency' => 'USD',
        ];

        if ($status === 'success') {
            $details['authorization_code'] = 'AUTH_' . strtoupper(uniqid());
            $details['settlement_date'] = now()->toDateString();
        } elseif ($status === 'failed') {
            $details['failure_reason'] = 'Insufficient funds';
            $details['failure_code'] = '102';
        } elseif ($status === 'refunded') {
            $details['refund_id'] = 'REF_' . strtoupper(uniqid());
            $details['refund_date'] = now()->subDays(rand(1, 30))->toDateString();
            $details['refund_reason'] = 'Customer request';
        }

        return $details;
    }
}