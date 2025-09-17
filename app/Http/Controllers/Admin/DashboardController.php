<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    protected $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * Get dashboard statistics
     * SEM-60: Admin Dashboard with KPI metrics and charts
     */
    public function getStats(): JsonResponse
    {
        try {
            $stats = $this->dashboardService->getKPIStats();

            // Log admin dashboard access
            AuditLog::log(
                auth()->id(),
                'Dashboard',
                'view_stats',
                ['timestamp' => now()]
            );

            return response()->json([
                'status' => 'success',
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch dashboard statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get revenue chart data
     */
    public function getRevenueChart(): JsonResponse
    {
        try {
            $revenueData = $this->dashboardService->getRevenueChartData();

            return response()->json([
                'status' => 'success',
                'data' => $revenueData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch revenue chart data'
            ], 500);
        }
    }

    /**
     * Get users chart data
     */
    public function getUsersChart(): JsonResponse
    {
        try {
            $userData = $this->dashboardService->getUsersChartData();

            return response()->json([
                'status' => 'success',
                'data' => $userData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch users chart data'
            ], 500);
        }
    }

    /**
     * Get properties chart data
     */
    public function getPropertiesChart(): JsonResponse
    {
        try {
            $propertyData = $this->dashboardService->getPropertiesChartData();

            return response()->json([
                'status' => 'success',
                'data' => $propertyData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch properties chart data'
            ], 500);
        }
    }
}
