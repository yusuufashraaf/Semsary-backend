<?php

namespace App\Console\Commands;

use App\Events\PropertyUpdated;
use Illuminate\Console\Command;
use App\Models\Property;


class TestPropertyStateChange extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Example: php artisan property:test-change 5 Valid
     */
    protected $signature = 'property:test-change {propertyId} {state=Valid}';

    /**
     * The console command description.
     */
    protected $description = 'Test property state change and fire PropertyUpdated event';

    public function handle()
    {
        $propertyId = $this->argument('propertyId');
        $newState   = $this->argument('state');

        $property = Property::find($propertyId);

        if (!$property) {
            $this->error("Property with ID {$propertyId} not found.");
            return 1;
        }

        $property->property_state = $newState;
        $property->save();

        // Fire event
        broadcast(new PropertyUpdated($property));

        $this->info("✅ Property #{$propertyId} updated to '{$newState}' and event fired.");
        return 0;
    }
}
