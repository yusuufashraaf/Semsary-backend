<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\User;
use Illuminate\Http\Request;
use App\Notifications\AgentAssigned;
use App\Events\ChatAssigned;
use App\Events\ChatUnassigned;
use Illuminate\Support\Facades\Notification;

class AdminChatController extends Controller
{
    // List all chats with possible agents
    public function index()
    {
        $chats = Chat::with(['property', 'assignedAgent','owner','property.owner'])->paginate(10);
        $agents = User::agents()->get(['id', 'first_name', 'last_name', 'email']); // only agents

        return response()->json([
            'success' => true,
            'data' => [
                'current_page' => $chats->currentPage(),
                'data' => $chats->items(),
                'total' => $chats->total(),
                'per_page' => $chats->perPage(),
                'agents' => $agents,
            ],
        ]);
    }

    // Show one chat
    public function show($id)
    {
        $chat = Chat::with(['property', 'assignedAgent'])->find($id);

        if (!$chat) {
            return response()->json(['success' => false, 'message' => 'Chat not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $chat]);
    }

    // Assign an agent
    public function assign(Request $request, $id)
    {
        $request->validate([
            'agent_id' => 'required|exists:users,id',
        ]);

        $chat = Chat::find($id);
        if (!$chat) {
            return response()->json(['success' => false, 'message' => 'Chat not found'], 404);
        }

        $chat->renter_id = $request->agent_id;
        $chat->save();

                // Get the agent
        $agent = User::find($request->agent_id);
        // Broadcast the event
        broadcast(new ChatAssigned($chat->id, $agent))->toOthers();
        // Send notification to the agent
        $agent->notify(new AgentAssigned($chat, $chat->property));

        return response()->json([
            'success' => true,
            'message' => 'Agent assigned successfully',
            'data' => $chat->load(['property', 'assignedAgent']),
        ]);
    }

    // Unassign agent
    public function unassign($id)
    {
        $chat = Chat::find($id);
        if (!$chat) {
            return response()->json(['success' => false, 'message' => 'Chat not found'], 404);
        }

        $chat->assigned_agent_id = null;
        $chat->save();

        return response()->json([
            'success' => true,
            'message' => 'Agent unassigned successfully',
            'data' => $chat->load(['property', 'assignedAgent']),
        ]);
    }

    // Get list of all agents
    public function agents()
    {
        $agents = User::agents()->get(['id', 'first_name', 'last_name', 'email']);

        return response()->json(['success' => true, 'data' => $agents]);
    }
}