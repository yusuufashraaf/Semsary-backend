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
            'reviewer' => $this->user?->name ?? 'Anonymous',
            'rating' => (int) $this->rating,
            'review' => $this->comment,
            'date' => $this->created_at?->toDateString(),
        ];
    }
}