<?php

namespace App\Services;

use App\Models\User;
use App\Models\Property;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class DashboardService
{
    public function getKPIStats(): array
    {
        return Cache::remember('admin_dashboard_stats', config('admin.dashboard.stats_cache_ttl', 60), function () {
            return [
                'totalUsers' => User::count(),
                'totalProperties' => Property::count(),
                'totalTransactions' => Transaction::count(),
                'totalRevenue' => Transaction::where('status', 'success')->sum('amount'),
                'usersByRole' => User::select('role', DB::raw('count(*) as count'))
                    ->groupBy('role')
                    ->pluck('count', 'role')
                    ->toArray(),
                'propertiesByStatus' => Property::select('property_state', DB::raw('count(*) as count'))
                    ->groupBy('property_state')
                    ->pluck('count', 'property_state')
                    ->toArray(),
                'transactionsByType' => Transaction::select('type', DB::raw('count(*) as count'))
                    ->groupBy('type')
                    ->pluck('count', 'type')
                    ->toArray(),
                'monthlyRevenue' => $this->getMonthlyRevenue(),
                'recentTransactions' => $this->getRecentTransactions(),
                'topProperties' => $this->getTopProperties(),
                'userGrowth' => $this->getUserGrowth(),
            ];
        });
    }

    public function getRevenueChartData(): array
    {
        return Cache::remember('revenue_chart_data', config('admin.dashboard.chart_cache_ttl', 120), function () {
            return Transaction::where('status', 'success')
                ->where('created_at', '>=', Carbon::now()->subMonths(12))
                ->select(
                    DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                    DB::raw('SUM(amount) as revenue'),
                    DB::raw('COUNT(*) as transaction_count')
                )
                ->groupBy('month')
                ->orderBy('month')
                ->get()
                ->toArray();
        });
    }

    public function getUsersChartData(): array
    {
        return Cache::remember('users_chart_data', config('admin.dashboard.chart_cache_ttl', 120), function () {
            return User::where('created_at', '>=', Carbon::now()->subMonths(12))
                ->select(
                    DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                    DB::raw('COUNT(*) as new_users'),
                    'role'
                )
                ->groupBy('month', 'role')
                ->orderBy('month')
                ->get()
                ->toArray();
        });
    }

    public function getPropertiesChartData(): array
    {
        return Cache::remember('properties_chart_data', config('admin.dashboard.chart_cache_ttl', 120), function () {
            return Property::where('created_at', '>=', Carbon::now()->subMonths(12))
                ->select(
                    DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                    DB::raw('COUNT(*) as new_properties'),
                    'property_state'
                )
                ->groupBy('month', 'property_state')
                ->orderBy('month')
                ->get()
                ->toArray();
        });
    }

    private function getMonthlyRevenue(): array
    {
        return Transaction::where('status', 'success')
            ->where('created_at', '>=', Carbon::now()->subMonths(12))
            ->select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('SUM(amount) as revenue')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('revenue', 'month')
            ->toArray();
    }

    private function getRecentTransactions(): array
    {
        return Transaction::with(['user', 'property'])
            ->latest()
            ->limit(10)
            ->get()
            ->toArray();
    }

    private function getTopProperties(): array
    {
        return Property::withCount('transactions')
            ->orderBy('transactions_count', 'desc')
            ->limit(10)
            ->get()
            ->toArray();
    }

    private function getUserGrowth(): array
    {
        return User::where('created_at', '>=', Carbon::now()->subDays(30))
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as new_users')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('new_users', 'date')
            ->toArray();
    }
}
