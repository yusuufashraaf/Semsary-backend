<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => fake()->optional(0.8)->dateTimeBetween('-1 year', 'now'),
            'password' => static::$password ??= Hash::make('password'),
            'role' => fake()->randomElement(['user', 'owner', 'agent', 'admin']),
            'phone_number' => fake()->optional(0.9)->phoneNumber(),
            'status' => fake()->randomElement(['active', 'pending', 'suspended']),
            'phone_verified_at' => fake()->optional(0.7)->dateTimeBetween('-1 year', 'now'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
            'phone_verified_at' => null,
        ]);
    }

    /**
     * Create a user with admin role.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'admin',
            'status' => 'active',
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ]);
    }

    /**
     * Create a user with owner role.
     */
    public function owner(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'owner',
            'status' => fake()->randomElement(['active', 'pending']),
            'email_verified_at' => fake()->optional(0.9)->dateTimeBetween('-1 year', 'now'),
        ]);
    }

    /**
     * Create a user with agent role (CS Agent).
     */
    public function agent(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'agent',
            'status' => fake()->randomElement(['active', 'pending']),
            'email_verified_at' => now(),
            'phone_verified_at' => fake()->optional(0.8)->dateTimeBetween('-1 year', 'now'),
        ]);
    }

    /**
     * Create an active CS agent.
     */
    public function csAgent(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'agent',
            'status' => 'active',
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ]);
    }

    /**
     * Create a regular user.
     */
    public function user(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'user',
            'status' => fake()->randomElement(['active', 'pending']),
        ]);
    }

    /**
     * Create an active user.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    /**
     * Create a pending user.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'email_verified_at' => null,
            'phone_verified_at' => null,
        ]);
    }

    /**
     * Create a suspended user.
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'suspended',
        ]);
    }

    /**
     * Create a user with verified email and phone.
     */
    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ]);
    }

    /**
     * Create a user with specific phone number.
     */
    public function withPhone(string $phoneNumber): static
    {
        return $this->state(fn (array $attributes) => [
            'phone_number' => $phoneNumber,
            'phone_verified_at' => now(),
        ]);
    }

    /**
     * Create a user with specific email.
     */
    public function withEmail(string $email): static
    {
        return $this->state(fn (array $attributes) => [
            'email' => $email,
        ]);
    }

    /**
     * Create a highly active CS agent (for testing performance metrics).
     */
    public function highPerformanceAgent(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'agent',
            'status' => 'active',
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
            'first_name' => fake()->randomElement(['Alex', 'Jordan', 'Taylor', 'Morgan', 'Casey']),
            'last_name' => fake()->randomElement(['Smith', 'Johnson', 'Williams', 'Brown', 'Davis']),
        ]);
    }
}
