<?php

require_once 'vendor/autoload.php';

// Initialize Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\DB;

echo "=== CS Agents Analysis ===\n";

// Count all agents
$totalAgents = User::where('role', 'agent')->count();
echo "Total agents with role='agent': {$totalAgents}\n";

// Count active agents
$activeAgents = User::where('role', 'agent')->where('status', 'active')->count();
echo "Active agents (status='active'): {$activeAgents}\n";

// Count by status
echo "\nAgents by status:\n";
$agentsByStatus = User::where('role', 'agent')
    ->select('status', DB::raw('count(*) as count'))
    ->groupBy('status')
    ->get();

foreach ($agentsByStatus as $item) {
    echo "  {$item->status}: {$item->count}\n";
}

// List all agents with their details
echo "\nAll agents details:\n";
$allAgents = User::where('role', 'agent')
    ->select('id', 'first_name', 'last_name', 'email', 'status', 'created_at')
    ->orderBy('id')
    ->get();

foreach ($allAgents as $agent) {
    echo "  ID: {$agent->id} | {$agent->first_name} {$agent->last_name} | {$agent->email} | Status: {$agent->status}\n";
}

// Test the updated scope
echo "\nTesting updated csAgents scope:\n";
$csAgentsCount = User::csAgents()->count();
echo "CS Agents via scope: {$csAgentsCount}\n";

$csAgents = User::csAgents()
    ->select('id', 'first_name', 'last_name', 'status')
    ->get();
    
foreach ($csAgents as $agent) {
    echo "  ID: {$agent->id} | {$agent->first_name} {$agent->last_name} | Status: {$agent->status}\n";
}

echo "\n=== Analysis Complete ===\n";
