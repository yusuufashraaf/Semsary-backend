<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Property;
use App\Models\CSAgentPropertyAssign;
use App\Models\Feature;

class CSAgentPropertyAssignSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin users if they don't exist
        $admins = User::admins()->get();
        if ($admins->count() < 2) {
            User::factory(2)->admin()->create();
        }

        // Create CS agents if they don't exist
        $agents = User::csAgents()->get();
        if ($agents->count() < 8) {
            $newAgentsCount = 8 - $agents->count();
            $newAgents = User::factory($newAgentsCount)->csAgent()->create();

            // Add some high-performance agents for testing
            User::factory(3)->highPerformanceAgent()->create();
        }

        // Create property owners if needed
        $owners = User::owners()->get();
        if ($owners->count() < 15) {
            $newOwnersCount = 15 - $owners->count();
            User::factory($newOwnersCount)->owner()->active()->create();
        }

        // Create features if they don't exist
        $featuresCount = Feature::count();
        if ($featuresCount < 20) {
            $features = [
                'Swimming Pool', 'Gym', 'Parking', 'Garden', 'Balcony', 'Elevator',
                'Security', 'Air Conditioning', 'Heating', 'Furnished', 'Internet',
                'Cable TV', 'Laundry', 'Storage', 'Fireplace', 'Terrace', 'Garage',
                'Basement', 'Attic', 'Walk-in Closet'
            ];

            foreach ($features as $featureName) {
                Feature::firstOrCreate(['name' => $featureName]);
            }
        }

        // Create properties if needed
        $properties = Property::all();
        if ($properties->count() < 50) {
            $newPropertiesCount = 50 - $properties->count();
            $owners = User::owners()->get();

            if ($owners->isEmpty()) {
                User::factory(5)->owner()->active()->create();
                $owners = User::owners()->get();
            }

            // Create properties manually since PropertyFactory doesn't exist
            for ($i = 0; $i < $newPropertiesCount; $i++) {
                $states = ['Pending', 'Valid', 'Rented', 'Sold'];
                $types = ['Apartment', 'Villa', 'Duplex', 'Roof', 'Land'];

                Property::create([
                    'owner_id' => $owners->random()->id,
                    'title' => 'Property ' . fake()->words(3, true),
                    'description' => fake()->paragraph(),
                    'bedrooms' => fake()->numberBetween(1, 5),
                    'bathrooms' => fake()->numberBetween(1, 3),
                    'type' => fake()->randomElement($types),
                    'price' => fake()->numberBetween(50000, 500000),
                    'price_type' => fake()->randomElement(['FullPay', 'Monthly', 'Daily']),
                    'location' => [
                        'address' => fake()->address(),
                        'city' => fake()->city(),
                        'lat' => fake()->latitude(),
                        'lng' => fake()->longitude(),
                    ],
                    'size' => fake()->numberBetween(100, 1000),
                    'property_state' => fake()->randomElement($states),
                ]);
            }
        }

        // Refresh our collections
        $admins = User::admins()->get();
        $agents = User::csAgents()->get();
        $properties = Property::all();

        $pendingProperties = $properties->where('property_state', 'Pending');
        if ($pendingProperties->isEmpty()) {
            $this->command->warn('No pending properties found for assignment creation.');
            return;
        }

        if ($agents->isEmpty()) {
            $this->command->warn('No CS agents found for assignment creation.');
            return;
        }

        if ($admins->isEmpty()) {
            $this->command->warn('No admin users found for assignment creation.');
            return;
        }

        // Create diverse CS Agent assignments
        // Create pending assignments (30%)
        CSAgentPropertyAssign::factory(25)
            ->pending()
            ->create([
                'cs_agent_id' => $agents->random()->id,
                'property_id' => $properties->where('property_state', 'Pending')->random()->id,
                'assigned_by' => $admins->random()->id,
            ]);

        // Create in-progress assignments (25%)
        CSAgentPropertyAssign::factory(20)
            ->inProgress()
            ->create([
                'cs_agent_id' => $agents->random()->id,
                'property_id' => $properties->random()->id,
                'assigned_by' => $admins->random()->id,
            ]);

        // Create completed assignments (35%)
        CSAgentPropertyAssign::factory(28)
            ->completed()
            ->create([
                'cs_agent_id' => $agents->random()->id,
                'property_id' => $properties->random()->id,
                'assigned_by' => $admins->random()->id,
            ]);

        // Create rejected assignments (10%)
        CSAgentPropertyAssign::factory(8)
            ->rejected()
            ->create([
                'cs_agent_id' => $agents->random()->id,
                'property_id' => $properties->random()->id,
                'assigned_by' => $admins->random()->id,
            ]);

        // Create some urgent assignments
        CSAgentPropertyAssign::factory(10)
            ->urgent()
            ->pending()
            ->create([
                'cs_agent_id' => $agents->random()->id,
                'property_id' => $properties->where('property_state', 'Pending')->random()->id,
                'assigned_by' => $admins->random()->id,
            ]);

        // Create some overdue assignments (for attention testing)
        CSAgentPropertyAssign::factory(5)
            ->overdue()
            ->create([
                'cs_agent_id' => $agents->random()->id,
                'property_id' => $properties->where('property_state', 'Pending')->random()->id,
                'assigned_by' => $admins->random()->id,
            ]);

        // Create some stale in-progress assignments
        CSAgentPropertyAssign::factory(4)
            ->stale()
            ->create([
                'cs_agent_id' => $agents->random()->id,
                'property_id' => $properties->random()->id,
                'assigned_by' => $admins->random()->id,
            ]);

        // Create some bulk assignments
        $bulkProperties = $properties->where('property_state', 'Pending')->take(8);
        $bulkAgent = $agents->random();
        $bulkAdmin = $admins->random();

        foreach ($bulkProperties as $property) {
            CSAgentPropertyAssign::factory()
                ->bulkAssignment()
                ->pending()
                ->create([
                    'cs_agent_id' => $bulkAgent->id,
                    'property_id' => $property->id,
                    'assigned_by' => $bulkAdmin->id,
                ]);
        }

        // Create some reassigned assignments
        CSAgentPropertyAssign::factory(6)
            ->reassigned()
            ->create([
                'cs_agent_id' => $agents->random()->id,
                'property_id' => $properties->random()->id,
                'assigned_by' => $admins->random()->id,
            ]);

        // Create assignments with different completion speeds
        CSAgentPropertyAssign::factory(10)
            ->quickCompletion()
            ->create([
                'cs_agent_id' => $agents->random()->id,
                'property_id' => $properties->random()->id,
                'assigned_by' => $admins->random()->id,
            ]);

        CSAgentPropertyAssign::factory(8)
            ->slowCompletion()
            ->create([
                'cs_agent_id' => $agents->random()->id,
                'property_id' => $properties->random()->id,
                'assigned_by' => $admins->random()->id,
            ]);

        // Create assignments for specific high-performance agents
        $topAgents = User::where('role', 'agent')
            ->where('status', 'active')
            ->where(function($query) {
                $query->where('first_name', 'LIKE', '%Alex%')
                      ->orWhere('first_name', 'LIKE', '%Jordan%')
                      ->orWhere('first_name', 'LIKE', '%Taylor%');
            })
            ->get();

        foreach ($topAgents as $agent) {
            // Give them more completed assignments
            CSAgentPropertyAssign::factory(15)
                ->completed()
                ->create([
                    'cs_agent_id' => $agent->id,
                    'property_id' => $properties->random()->id,
                    'assigned_by' => $admins->random()->id,
                ]);

            // Give them some quick completions
            CSAgentPropertyAssign::factory(5)
                ->quickCompletion()
                ->create([
                    'cs_agent_id' => $agent->id,
                    'property_id' => $properties->random()->id,
                    'assigned_by' => $admins->random()->id,
                ]);

            // Give them current assignments
            CSAgentPropertyAssign::factory(3)
                ->inProgress()
                ->create([
                    'cs_agent_id' => $agent->id,
                    'property_id' => $properties->random()->id,
                    'assigned_by' => $admins->random()->id,
                ]);
        }
    }
}
