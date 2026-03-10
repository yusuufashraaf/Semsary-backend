<?php

namespace App\Console\Commands;

use App\Events\UserUpdated as EventsUserUpdated;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class StatusChanged extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'change:status {--state=}';

    /**
     * The console command description.
     */
    protected $description = 'Test broadcasting user update';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $first_name = $this->option('state');

        Log::info("Command fired: change:status", [
            'first_name' => $first_name,
        ]);

        // Find user (for example user ID = 1)
        $user = User::find(1);
        if (!$user) {
            $this->error("User not found!");
            return;
        }

        // Update status to simulate a change
        $user->first_name = $first_name;
        $user->save();

        // Broadcast the event
        broadcast(new EventsUserUpdated($user));

        $this->info("UserUpdated event dispatched for user ID {$user->id}");
    }
}
