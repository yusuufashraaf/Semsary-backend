<?php

namespace App\Events;

use App\Models\CSAgentPropertyAssign;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PropertyAssigned
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public CSAgentPropertyAssign $assignment;

    /**
     * Create a new event instance.
     */
    public function __construct(CSAgentPropertyAssign $assignment)
    {
        $this->assignment = $assignment;
    }
}
