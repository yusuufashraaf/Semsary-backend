<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;


class UserStatisticsResource extends JsonResource
{
    /**
     * Transform user statistics into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'total_users' => $this->resource['total_users'] ?? 0,
            'active_users' => $this->resource['active_users'] ?? 0,
            'pending_users' => $this->resource['pending_users'] ?? 0,
            'suspended_users' => $this->resource['suspended_users'] ?? 0,
            'users_by_role' => $this->resource['users_by_role'] ?? [],
            'recent_registrations' => $this->resource['recent_registrations'] ?? 0,
            'recent_admin_actions' => $this->resource['recent_admin_actions'] ?? 0,
        ];
    }
}
