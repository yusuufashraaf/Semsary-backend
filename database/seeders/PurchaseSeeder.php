<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Purchase;
use App\Models\User;
use App\Models\Property;
use App\Models\RentRequest;

class PurchaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        $properties = Property::all();
        $rentRequests = RentRequest::all();

        $statuses = ['pending', 'successful', 'failed', 'refunded'];
        $paymentTypes = ['rent', 'deposit', 'refund', 'payout', 'full_payment'];
        $paymentGateways = ['PayMob', 'PayPal', 'Fawry', 'Stripe', 'Wallet'];

        foreach ($users as $user) {
            // Create 2-5 purchases per user
            $purchaseCount = rand(2, 5);
            
            for ($i = 0; $i < $purchaseCount; $i++) {
                $property = $properties->random();
                $rentRequest = $rentRequests->where('user_id', $user->id)->where('property_id', $property->id)->first();
                
                $paymentType = $paymentTypes[array_rand($paymentTypes)];
                $status = $statuses[array_rand($statuses)];
                
                // Adjust amount based on payment type
                $amount = $this->calculateAmount($property, $paymentType);
                
                Purchase::create([
                    'user_id' => $user->id,
                    'property_id' => $property->id,
                    'rent_request_id' => $rentRequest?->id,
                    'amount' => $amount,
                    'deposit_amount' => $paymentType === 'deposit' ? $amount : null,
                    'payment_type' => $paymentType,
                    'status' => $status,
                    'payment_gateway' => $paymentGateways[array_rand($paymentGateways)],
                    'transaction_ref' => 'TXN_' . uniqid() . '_' . rand(1000, 9999),
                    'idempotency_key' => 'idemp_' . uniqid(),
                    'payment_details' => $this->generatePaymentDetails($status, $paymentType),
                    'metadata' => $this->generateMetadata($status, $paymentType, $property),
                    'created_at' => now()->subDays(rand(0, 90)),
                ]);
            }
        }

        $this->command->info('Purchases seeded successfully!');
    }

    /**
     * Calculate amount based on payment type and property
     */
    private function calculateAmount($property, $paymentType): float
    {
        return match($paymentType) {
            'deposit' => $property->price * 0.1, // 10% deposit
            'rent' => $property->price * rand(1, 3), // 1-3 months rent
            'refund' => $property->price * 0.1, // Refund amount
            'payout' => $property->price * 0.9, // Payout to owner (90%)
            default => $property->price, // Full payment
        };
    }

    /**
     * Generate payment details based on status
     */
    private function generatePaymentDetails($status, $paymentType): array
    {
        $details = [
            'payment_method' => 'credit_card',
            'card_last_four' => rand(1000, 9999),
            'currency' => 'USD',
            'payment_type' => $paymentType,
        ];

        if ($status === 'successful') {
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

    /**
     * Generate metadata based on status and payment type
     */
    private function generateMetadata($status, $paymentType, $property): array
    {
        return [
            'property_title' => $property->title,
            'property_type' => $property->type,
            'payment_category' => $paymentType,
            'processed_at' => now()->toDateTimeString(),
            'additional_notes' => $status === 'successful' ? 'Payment processed successfully' : 'Payment ' . $status,
        ];
    }
}