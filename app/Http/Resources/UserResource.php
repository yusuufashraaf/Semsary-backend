<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\User;


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
            'id_image_url'         => $this->id_image_url,
            'id_state'         => $this->id_stat,
            'status'       => $this->status,
            'created_at'   => $this->created_at?->toDateTimeString(), // Format dates for consistency
            'email_verified_at' => $this->email_verified_at?->format('Y-m-d H:i:s'),
            'phone_verified_at' => $this->phone_verified_at?->format('Y-m-d H:i:s'),
            // Counts
            'properties_count' => $this->when(isset($this->properties_count), $this->properties_count),
            'transactions_count' => $this->when(isset($this->transactions_count), $this->transactions_count),
        ];
    }
}