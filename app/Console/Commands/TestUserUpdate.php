<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Events\UserUpdated;

class TestUserUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Example usage:
     * php artisan user:test-update 5 status active
     * php artisan user:test-update 5 id_state valid
     * php artisan user:test-update 5 role agent
     */
    protected $signature = 'user:test-update {userId} {field} {value}';

    /**
     * The console command description.
     */
    protected $description = 'Test user updates (status, id_state, role) and fire UserUpdated event';

    public function handle()
    {
        $userId = $this->argument('userId');
        $field  = $this->argument('field');   // "status", "id_state", "role"
        $value  = $this->argument('value');

        $user = User::find($userId);

        if (!$user) {
            $this->error("❌ User with ID {$userId} not found.");
            return 1;
        }

        // Validate field + value
        if ($field === 'status') {
            $validStatuses = ['active', 'suspended', 'inactive'];
            if (!in_array($value, $validStatuses)) {
                $this->error("❌ Invalid status. Valid: " . implode(', ', $validStatuses));
                return 1;
            }
        } elseif ($field === 'id_state') {
            $validIdStates = ['valid', 'rejected', 'pending'];
            if (!in_array($value, $validIdStates)) {
                $this->error("❌ Invalid id_state. Valid: " . implode(', ', $validIdStates));
                return 1;
            }
        } elseif ($field === 'role') {
            $validRoles = ['user', 'agent', 'owner', 'admin'];
            if (!in_array($value, $validRoles)) {
                $this->error("❌ Invalid role. Valid: " . implode(', ', $validRoles));
                return 1;
            }
        } else {
            $this->error("❌ Unsupported field '{$field}'. Use 'status', 'id_state', or 'role'.");
            return 1;
        }

        // Track old value before update
        $oldValue = $user->{$field};

        // Update user
        $user->update([$field => $value]);

        // Fire broadcast event
        broadcast(new UserUpdated($user));

        $this->info("✅ User #{$userId} {$field} updated from '{$oldValue}' → '{$value}' and event fired.");
        return 0;
    }
}
