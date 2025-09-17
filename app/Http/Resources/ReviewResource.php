<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reviewer' => $this->user
                ? trim($this->user->first_name . ' ' . $this->user->last_name)
                : 'Anonymous',
            'rating' => (int) $this->rating,
            'review' => $this->comment,
            'date' => $this->created_at?->toDateString(),
        ];
    }
}