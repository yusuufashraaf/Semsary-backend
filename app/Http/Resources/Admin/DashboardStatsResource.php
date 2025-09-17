<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardStatsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'totalUsers' => $this['totalUsers'],
            'totalProperties' => $this['totalProperties'],
            'totalTransactions' => $this['totalTransactions'],
            'totalRevenue' => number_format($this['totalRevenue'], 2),
            'usersByRole' => $this['usersByRole'],
            'propertiesByStatus' => $this['propertiesByStatus'],
            'transactionsByType' => $this['transactionsByType'],
            'monthlyRevenue' => $this['monthlyRevenue'],
            'recentTransactions' => $this['recentTransactions'],
            'topProperties' => $this['topProperties'],
            'userGrowth' => $this['userGrowth'],
            'lastUpdated' => now()->toISOString(),
        ];
    }
}
