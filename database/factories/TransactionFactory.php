<?php

namespace Database\Factories;

use App\Models\Transaction;
use App\Models\User;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'property_id' => Property::factory(),
            'type' => $this->faker->randomElement(['rent', 'buy']),
            'status' => $this->faker->randomElement(['pending', 'success', 'failed', 'refunded']),
            'amount' => $this->faker->randomFloat(2, 100, 10000),
            'deposit_amount' => $this->faker->randomFloat(2, 50, 1000),
            'payment_gateway' => $this->faker->randomElement(['PayMob', 'PayPal', 'Fawry']),
        ];
    }

    public function success(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'success',
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    public function rent(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'rent',
        ]);
    }

    public function buy(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'buy',
        ]);
    }
}
