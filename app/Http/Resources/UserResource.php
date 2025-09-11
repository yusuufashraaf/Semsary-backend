<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // This array defines the exact structure of the JSON response.
        // Only include fields that are safe for the public to see.
        return [
            'id'           => $this->id,
            'first_name'   => $this->first_name,
            'last_name'    => $this->last_name,
            'email'        => $this->email,
            'phone_number' => $this->phone_number,
            'role'         => $this->role,
            'status'       => $this->status,
            'created_at'   => $this->created_at->toDateTimeString(), // Format dates for consistency
        ];
    }
}
