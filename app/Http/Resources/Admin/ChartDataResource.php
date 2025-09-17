<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChartDataResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'chartData' => $this->resource,
            'generated_at' => now()->toISOString(),
            'data_points' => count($this->resource),
        ];
    }
}
