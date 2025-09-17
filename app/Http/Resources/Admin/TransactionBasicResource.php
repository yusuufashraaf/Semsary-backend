<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionBasicResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type ?? 'unknown',
            'amount' => $this->amount ?? 0,
            'status' => $this->status ?? 'unknown',
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
