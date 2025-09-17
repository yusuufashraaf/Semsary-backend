<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserDetailResource extends JsonResource
{
    /**
     * Transform the resource for detailed view.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->first_name . ' ' . $this->last_name,
            'email' => $this->email,
            'phone_number' => $this->phone_number,
            'role' => $this->role,
            'status' => $this->status,
            'email_verified_at' => $this->email_verified_at?->format('Y-m-d H:i:s'),
            'phone_verified_at' => $this->phone_verified_at?->format('Y-m-d H:i:s'),
            'id_image_url' => $this->id_image_url,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),

            // Actual counts from loaded relationships
            'properties_count' => $this->properties_count ?? 0,
            'transactions_count' => $this->transactions_count ?? 0,

            // Recent data from loaded relationships
            'recent_properties' => $this->when(
                $this->relationLoaded('properties'),
                PropertyBasicResource::collection($this->properties)
            ),
            'recent_transactions' => $this->when(
                $this->relationLoaded('transactions'),
                TransactionBasicResource::collection($this->transactions)
            ),
            'admin_actions' => $this->when(
                $this->relationLoaded('adminActions'),
                AdminActionResource::collection($this->adminActions)
            ),
        ];
    }
}
