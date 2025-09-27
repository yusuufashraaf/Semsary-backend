<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;


    public User $user;

    public function __construct(User $user)
    {
        $this->user = $user->fresh();
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel("user.{$this->user->id}")];
    }

    public function broadcastAs(): string
    {
        return "user.updated";
    }

    public function broadcastWith(): array
    {
        // control what gets sent to the frontend
        return [
            "id"            => $this->user->id,
            "firstName"     => $this->user->first_name,
            "lastName"      => $this->user->last_name,
            "email"         => $this->user->email,
            "phoneNumber"   => $this->user->phone_number,
            "isEmailVerified" => $this->user->hasVerifiedEmail(),
            "isPhoneVerified" => (bool) $this->user->phone_verified_at,
            "role"          => $this->user->role,
            "status"        => $this->user->status,
            "idUpladed"     => $this->user->id_document_path,
            'id_state'        => $this->user->id_state,
        ];
    }
}
