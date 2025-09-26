<?php

namespace Database\Factories;

use App\Models\CSAgentPropertyAssign;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CSAgentPropertyAssign>
 */
class CSAgentPropertyAssignFactory extends Factory
{
    protected $model = CSAgentPropertyAssign::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $assignedAt = fake()->dateTimeBetween('-30 days', 'now');
        $status = fake()->randomElement(CSAgentPropertyAssign::getStatuses());
        $priority = fake()->randomElement(['low', 'normal', 'high', 'urgent']);

        // Generate realistic timestamps based on status
        $startedAt = null;
        $completedAt = null;

        if (in_array($status, ['in_progress', 'completed', 'rejected'])) {
            $startedAt = fake()->dateTimeBetween($assignedAt, 'now');
        }

        if (in_array($status, ['completed', 'rejected'])) {
            $completedAt = fake()->dateTimeBetween($startedAt ?? $assignedAt, 'now');
        }

        return [
            'property_id' => function() {
                // Try to get an existing property first, create one if none exist
                return Property::inRandomOrder()->first()?->id ??
                       Property::create([
                           'owner_id' => User::factory()->owner()->create()->id,
                           'title' => 'Factory Property ' . fake()->words(2, true),
                           'description' => fake()->paragraph(),
                           'bedrooms' => fake()->numberBetween(1, 4),
                           'bathrooms' => fake()->numberBetween(1, 3),
                           'type' => fake()->randomElement(['Apartment', 'Villa', 'Duplex', 'Roof', 'Land']),
                           'price' => fake()->numberBetween(50000, 300000),
                           'price_type' => fake()->randomElement(['FullPay', 'Monthly', 'Daily']),
                           'location' => [
                               'address' => fake()->address(),
                               'city' => fake()->city(),
                               'lat' => fake()->latitude(),
                               'lng' => fake()->longitude(),
                           ],
                           'size' => fake()->numberBetween(100, 800),
                           'property_state' => fake()->randomElement(['Pending', 'Valid', 'Rented', 'Sold']),
                       ])->id;
            },
            'cs_agent_id' => User::factory()->agent(),
            'assigned_by' => User::factory()->admin(),
            'status' => $status,
            'notes' => fake()->optional(0.7)->paragraphs(fake()->numberBetween(1, 3), true),
            'metadata' => [
                'priority' => $priority,
                'assigned_by_name' => fake()->name(),
                'bulk_assignment' => fake()->boolean(20), // 20% chance of bulk assignment
            ],
            'assigned_at' => $assignedAt,
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
        ];
    }

    /**
     * Create a pending assignment
     */
    public function pending(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => CSAgentPropertyAssign::STATUS_PENDING,
                'started_at' => null,
                'completed_at' => null,
                'notes' => fake()->optional(0.3)->sentence(),
            ];
        });
    }

    /**
     * Create an in-progress assignment
     */
    public function inProgress(): static
    {
        return $this->state(function (array $attributes) {
            $assignedAt = $attributes['assigned_at'] ?? fake()->dateTimeBetween('-15 days', 'now');
            $startedAt = fake()->dateTimeBetween($assignedAt, 'now');

            return [
                'status' => CSAgentPropertyAssign::STATUS_IN_PROGRESS,
                'assigned_at' => $assignedAt,
                'started_at' => $startedAt,
                'completed_at' => null,
                'notes' => fake()->optional(0.6)->paragraphs(fake()->numberBetween(1, 2), true),
            ];
        });
    }

    /**
     * Create a completed assignment
     */
    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            $assignedAt = $attributes['assigned_at'] ?? fake()->dateTimeBetween('-30 days', '-1 day');
            $startedAt = fake()->dateTimeBetween($assignedAt, Carbon::parse($assignedAt)->addMinute(),);
            $completedAt = fake()->dateTimeBetween($startedAt, Carbon::parse($startedAt)->addMinute(),);

            return [
                'status' => CSAgentPropertyAssign::STATUS_COMPLETED,
                'assigned_at' => $assignedAt,
                'started_at' => $startedAt,
                'completed_at' => $completedAt,
                'notes' => fake()->paragraphs(fake()->numberBetween(2, 4), true),
            ];
        });
    }

    /**
     * Create a rejected assignment
     */
    public function rejected(): static
    {
        return $this->state(function (array $attributes) {
            $assignedAt = $attributes['assigned_at'] ?? fake()->dateTimeBetween('-30 days', '-1 day');
            $startedAt = fake()->optional(0.6)->dateTimeBetween($assignedAt, Carbon::parse($assignedAt)->addMinute(),);
            $completedAt = fake()->dateTimeBetween($startedAt ?? $assignedAt, Carbon::parse($startedAt ?? $assignedAt)->addMinute(),);

            $rejectionReasons = [
                'Property information incomplete',
                'Unable to contact property owner',
                'Property no longer available',
                'Documentation issues found',
                'Property does not meet standards',
                'Owner decided not to proceed',
                'Duplicate property listing detected',
            ];

            return [
                'status' => CSAgentPropertyAssign::STATUS_REJECTED,
                'assigned_at' => $assignedAt,
                'started_at' => $startedAt,
                'completed_at' => $completedAt,
                'notes' => 'Rejection reason: ' . fake()->randomElement($rejectionReasons) . "\n\n" . fake()->optional(0.8)->paragraphs(1, true),
            ];
        });
    }

    /**
     * Create an urgent assignment
     */
    public function urgent(): static
    {
        return $this->state(function (array $attributes) {
            $metadata = $attributes['metadata'] ?? [];
            $metadata['priority'] = 'urgent';

            return [
                'metadata' => $metadata,
                'assigned_at' => fake()->dateTimeBetween('-3 days', 'now'),
            ];
        });
    }

    /**
     * Create a high priority assignment
     */
    public function highPriority(): static
    {
        return $this->state(function (array $attributes) {
            $metadata = $attributes['metadata'] ?? [];
            $metadata['priority'] = 'high';

            return [
                'metadata' => $metadata,
            ];
        });
    }

    /**
     * Create an overdue assignment (pending for more than 7 days)
     */
    public function overdue(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => CSAgentPropertyAssign::STATUS_PENDING,
                'assigned_at' => fake()->dateTimeBetween('-20 days', '-8 days'),
                'started_at' => null,
                'completed_at' => null,
            ];
        });
    }

    /**
     * Create a stale in-progress assignment (in progress for more than 3 days)
     */
    public function stale(): static
    {
        return $this->state(function (array $attributes) {
            $assignedAt = fake()->dateTimeBetween('-15 days', '-5 days');
            $startedAt = fake()->dateTimeBetween($assignedAt, Carbon::parse($assignedAt)->addMinute(),);

            return [
                'status' => CSAgentPropertyAssign::STATUS_IN_PROGRESS,
                'assigned_at' => $assignedAt,
                'started_at' => $startedAt,
                'completed_at' => null,
            ];
        });
    }

    /**
     * Create a bulk assignment
     */
    public function bulkAssignment(): static
    {
        return $this->state(function (array $attributes) {
            $metadata = $attributes['metadata'] ?? [];
            $metadata['bulk_assignment'] = true;

            return [
                'metadata' => $metadata,
                'assigned_at' => fake()->dateTimeBetween('-7 days', 'now'),
            ];
        });
    }

    /**
     * Create a reassigned assignment
     */
    public function reassigned(): static
    {
        return $this->state(function (array $attributes) {
            $metadata = $attributes['metadata'] ?? [];
            $metadata['reassigned'] = true;
            $metadata['previous_agent_id'] = User::factory()->agent()->create()->id;
            $metadata['reassignment_reason'] = fake()->randomElement([
                'Agent overloaded with assignments',
                'Agent unavailable due to leave',
                'Better skill match with new agent',
                'Geographic proximity to property',
            ]);
            $metadata['reassigned_at'] = Carbon::parse(fake()->dateTimeBetween('-5 days', 'now'))->toISOString();
            $metadata['reassigned_by'] = fake()->name();

            return [
                'status' => CSAgentPropertyAssign::STATUS_PENDING,
                'metadata' => $metadata,
                'assigned_at' => fake()->dateTimeBetween('-5 days', 'now'),
                'started_at' => null,
                'completed_at' => null,
            ];
        });
    }

    /**
     * Create assignment with specific CS agent
     */
    public function forAgent(User $agent): static
    {
        return $this->state(function (array $attributes) use ($agent) {
            return [
                'cs_agent_id' => $agent->id,
            ];
        });
    }

    /**
     * Create assignment with specific property
     */
    public function forProperty(Property $property): static
    {
        return $this->state(function (array $attributes) use ($property) {
            return [
                'property_id' => $property->id,
            ];
        });
    }

    /**
     * Create assignment assigned by specific admin
     */
    public function assignedBy(User $admin): static
    {
        return $this->state(function (array $attributes) use ($admin) {
            $metadata = $attributes['metadata'] ?? [];
            $metadata['assigned_by_name'] = $admin->full_name;

            return [
                'assigned_by' => $admin->id,
                'metadata' => $metadata,
            ];
        });
    }

    /**
     * Create assignment with quick completion (completed within 24 hours)
     */
    public function quickCompletion(): static
    {
        return $this->state(function (array $attributes) {
            $assignedAt = fake()->dateTimeBetween('-10 days', '-2 days');
            $startedAt = fake()->dateTimeBetween($assignedAt, Carbon::parse($assignedAt)->addHours(4));
            $completedAt = fake()->dateTimeBetween($startedAt, Carbon::parse($startedAt)->addHours(20));

            return [
                'status' => CSAgentPropertyAssign::STATUS_COMPLETED,
                'assigned_at' => $assignedAt,
                'started_at' => $startedAt,
                'completed_at' => $completedAt,
                'notes' => 'Quick completion: ' . fake()->sentence(),
            ];
        });
    }

    /**
     * Create assignment with slow completion (completed after 5+ days)
     */
    public function slowCompletion(): static
    {
        return $this->state(function (array $attributes) {
            $assignedAt = fake()->dateTimeBetween('-20 days', '-10 days');
            $startedAt = fake()->dateTimeBetween($assignedAt, Carbon::parse($assignedAt)->addDays(2));
            $completedAt = fake()->dateTimeBetween(Carbon::parse($startedAt)->addDays(5), Carbon::parse($startedAt)->addDays(15));

            return [
                'status' => CSAgentPropertyAssign::STATUS_COMPLETED,
                'assigned_at' => $assignedAt,
                'started_at' => $startedAt,
                'completed_at' => $completedAt,
                'notes' => 'Extended completion time due to: ' . fake()->sentence(),
            ];
        });
    }
}