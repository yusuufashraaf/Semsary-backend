<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminActionResource extends JsonResource
{
    /**
     * Transform the admin action resource.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'reason' => $this->reason,
            'performed_at' => $this->created_at->format('Y-m-d H:i:s'),
            'admin' => $this->when($this->relationLoaded('admin') && $this->admin, [
                'id' => $this->admin->id,
                'name' => $this->admin->first_name . ' ' . $this->admin->last_name,
                'email' => $this->admin->email,
            ]),
        ];
    }
}
