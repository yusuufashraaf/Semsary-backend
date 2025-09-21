<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\DashboardStatsResource;
use App\Models\CSAgentPropertyAssign;
use App\Models\User;
use App\Models\Property;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CSAgentDashboardController extends Controller
{
    /**
     * Get CS Agent dashboard overview data
     * SEM-64: CS Agent Dashboard API Implementation
     */
    public function getDashboardData(): JsonResponse
    {
        try {
            $dashboardData = [
                'metrics' => $this->getKPIMetrics(),
                'recent_activity' => $this->getRecentActivity(),
                'assignments_overview' => $this->getAssignmentsOverview(),
                'agent_performance' => $this->getAgentPerformance(),
                'pending_actions' => $this->getPendingActions(),
            ];

            // Log admin dashboard access
            AuditLog::log(
                auth()->id(),
                'CSAgentDashboard',
                'view_dashboard',
                ['timestamp' => now()]
            );

            return response()->json([
                'status' => 'success',
                'data' => $dashboardData,
                'generated_at' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch CS Agent dashboard data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get key performance indicators for CS Agent operations
     */
    private function getKPIMetrics(): array
    {
        $today = now()->startOfDay();
        $thisWeek = now()->startOfWeek();
        $thisMonth = now()->startOfMonth();

        return [
            // Total assignments
            'total_assignments' => [
                'value' => CSAgentPropertyAssign::count(),
                'change_from_last_month' => $this->getChangePercentage(
                    CSAgentPropertyAssign::where('assigned_at', '>=', $thisMonth)->count(),
                    CSAgentPropertyAssign::whereBetween('assigned_at', [
                        $thisMonth->copy()->subMonth(),
                        $thisMonth
                    ])->count()
                ),
                'trend' => 'up'
            ],

            // Pending assignments
            'pending_assignments' => [
                'value' => CSAgentPropertyAssign::pending()->count(),
                'urgent_count' => CSAgentPropertyAssign::pending()
                    ->whereJsonContains('metadata->priority', 'urgent')->count(),
                'overdue_count' => CSAgentPropertyAssign::pending()
                    ->where('assigned_at', '<=', now()->subDays(7))->count(),
            ],

            // In-progress assignments
            'in_progress_assignments' => [
                'value' => CSAgentPropertyAssign::inProgress()->count(),
                'today' => CSAgentPropertyAssign::inProgress()
                    ->whereDate('started_at', $today)->count(),
                'this_week' => CSAgentPropertyAssign::inProgress()
                    ->where('started_at', '>=', $thisWeek)->count(),
            ],

            // Completed assignments
            'completed_assignments' => [
                'value' => CSAgentPropertyAssign::completed()->count(),
                'today' => CSAgentPropertyAssign::completed()
                    ->whereDate('completed_at', $today)->count(),
                'this_week' => CSAgentPropertyAssign::completed()
                    ->where('completed_at', '>=', $thisWeek)->count(),
                'this_month' => CSAgentPropertyAssign::completed()
                    ->where('completed_at', '>=', $thisMonth)->count(),
            ],

            // Completion rate
            'completion_rate' => [
                'value' => $this->getCompletionRate(),
                'this_month' => $this->getCompletionRate($thisMonth),
                'target' => 85, // Target completion rate percentage
            ],

            // Average completion time
            'avg_completion_time' => [
                'value' => $this->getAverageCompletionTime(),
                'unit' => 'hours',
                'this_month' => $this->getAverageCompletionTime($thisMonth),
                'target' => 24 // Target completion time in hours
            ],

            // Active CS agents
            'active_agents' => [
                'value' => User::csAgents()->count(),
                'with_assignments' => User::csAgents()
                    ->whereHas('csAgentAssignments', function ($query) {
                        $query->whereIn('status', ['pending', 'in_progress']);
                    })->count(),
                'available' => User::csAgents()
                    ->whereDoesntHave('csAgentAssignments', function ($query) {
                        $query->whereIn('status', ['pending', 'in_progress']);
                    })->count(),
            ]
        ];
    }

    /**
     * Get recent activity for CS Agent operations
     */
    private function getRecentActivity(int $limit = 10): array
    {
        $recentAssignments = CSAgentPropertyAssign::with([
            'property:id,title,type,property_state',
            'csAgent:id,first_name,last_name,email',
            'assignedBy:id,first_name,last_name'
        ])
        ->orderBy('updated_at', 'desc')
        ->limit($limit)
        ->get()
        ->map(function ($assignment) {
            return [
                'id' => $assignment->id,
                'type' => 'assignment_' . $assignment->status,
                'title' => "Assignment {$assignment->formatted_status}",
                'description' => "Property '{$assignment->property->title}' {$assignment->status} by {$assignment->csAgent->full_name}",
                'property' => [
                    'id' => $assignment->property->id,
                    'title' => $assignment->property->title,
                    'type' => $assignment->property->type,
                ],
                'agent' => [
                    'id' => $assignment->csAgent->id,
                    'name' => $assignment->csAgent->full_name,
                ],
                'status' => $assignment->status,
                'priority' => $assignment->priority,
                'timestamp' => $assignment->updated_at->toISOString(),
                'time_ago' => $assignment->updated_at->diffForHumans(),
            ];
        });

        return $recentAssignments->toArray();
    }

    /**
     * Get assignments overview with status breakdown
     */
    private function getAssignmentsOverview(): array
    {
        $statusCounts = CSAgentPropertyAssign::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $statuses = CSAgentPropertyAssign::getStatuses();
        $overview = [];

        foreach ($statuses as $status) {
            $count = $statusCounts[$status] ?? 0;
            $overview[] = [
                'status' => $status,
                'count' => $count,
                'label' => ucfirst(str_replace('_', ' ', $status)),
                'percentage' => $this->calculatePercentage($count, array_sum($statusCounts)),
                'color' => $this->getStatusColor($status),
            ];
        }

        // Priority breakdown
        $priorityCounts = CSAgentPropertyAssign::selectRaw('JSON_EXTRACT(metadata, "$.priority") as priority, COUNT(*) as count')
            ->whereNotNull('metadata')
            ->groupBy(DB::raw('JSON_EXTRACT(metadata, "$.priority")'))
            ->pluck('count', 'priority')
            ->toArray();

        $priorityOverview = [];
        $priorities = ['low', 'normal', 'high', 'urgent'];

        foreach ($priorities as $priority) {
            $count = $priorityCounts['"' . $priority . '"'] ?? 0;
            $priorityOverview[] = [
                'priority' => $priority,
                'count' => $count,
                'label' => ucfirst($priority),
                'percentage' => $this->calculatePercentage($count, array_sum($priorityCounts)),
            ];
        }

        return [
            'by_status' => $overview,
            'by_priority' => $priorityOverview,
            'total' => array_sum($statusCounts),
        ];
    }

    /**
     * Get agent performance metrics
     */
    private function getAgentPerformance(int $limit = 5): array
    {
        $topPerformers = User::csAgents()
            ->withCount([
                'csAgentAssignments as total_assignments',
                'csAgentAssignments as completed_assignments' => function ($query) {
                    $query->where('status', 'completed');
                },
                'csAgentAssignments as pending_assignments' => function ($query) {
                    $query->where('status', 'pending');
                },
                'csAgentAssignments as in_progress_assignments' => function ($query) {
                    $query->where('status', 'in_progress');
                }
            ])
            ->having('total_assignments', '>', 0)
            ->orderByDesc('completed_assignments')
            ->limit($limit)
            ->get()
            ->map(function ($agent) {
                $completionRate = $agent->total_assignments > 0
                    ? round(($agent->completed_assignments / $agent->total_assignments) * 100, 2)
                    : 0;

                return [
                    'id' => $agent->id,
                    'name' => $agent->full_name,
                    'email' => $agent->email,
                    'total_assignments' => $agent->total_assignments,
                    'completed_assignments' => $agent->completed_assignments,
                    'pending_assignments' => $agent->pending_assignments,
                    'in_progress_assignments' => $agent->in_progress_assignments,
                    'completion_rate' => $completionRate,
                    'average_completion_time' => $agent->getAverageCompletionTime(),
                    'workload_status' => $this->getWorkloadStatus($agent->pending_assignments + $agent->in_progress_assignments),
                ];
            });

        return $topPerformers->toArray();
    }

    /**
     * Get pending actions that require attention
     */
    private function getPendingActions(): array
    {
        return [
            'overdue_assignments' => CSAgentPropertyAssign::pending()
                ->where('assigned_at', '<=', now()->subDays(7))
                ->with(['property:id,title', 'csAgent:id,first_name,last_name'])
                ->limit(5)
                ->get()
                ->map(function ($assignment) {
                    return [
                        'id' => $assignment->id,
                        'property_title' => $assignment->property->title,
                        'agent_name' => $assignment->csAgent->full_name,
                        'days_overdue' => now()->diffInDays($assignment->assigned_at),
                        'priority' => $assignment->priority,
                    ];
                }),

            'urgent_assignments' => CSAgentPropertyAssign::whereIn('status', ['pending', 'in_progress'])
                ->whereJsonContains('metadata->priority', 'urgent')
                ->with(['property:id,title', 'csAgent:id,first_name,last_name'])
                ->limit(5)
                ->get()
                ->map(function ($assignment) {
                    return [
                        'id' => $assignment->id,
                        'property_title' => $assignment->property->title,
                        'agent_name' => $assignment->csAgent->full_name,
                        'status' => $assignment->status,
                        'assigned_at' => $assignment->assigned_at->toISOString(),
                    ];
                }),

            'stale_in_progress' => CSAgentPropertyAssign::inProgress()
                ->where('started_at', '<=', now()->subDays(3))
                ->with(['property:id,title', 'csAgent:id,first_name,last_name'])
                ->limit(5)
                ->get()
                ->map(function ($assignment) {
                    return [
                        'id' => $assignment->id,
                        'property_title' => $assignment->property->title,
                        'agent_name' => $assignment->csAgent->full_name,
                        'days_in_progress' => now()->diffInDays($assignment->started_at),
                        'started_at' => $assignment->started_at->toISOString(),
                    ];
                }),

            'unassigned_properties' => Property::unassigned()
                ->where('property_state', 'Pending')
                ->with(['owner:id,first_name,last_name'])
                ->limit(5)
                ->get()
                ->map(function ($property) {
                    return [
                        'id' => $property->id,
                        'title' => $property->title,
                        'type' => $property->type,
                        'owner_name' => $property->owner->full_name,
                        'created_at' => $property->created_at->toISOString(),
                        'days_pending' => now()->diffInDays($property->created_at),
                    ];
                }),
        ];
    }

    /**
     * Calculate percentage with proper handling of zero division
     */
    private function calculatePercentage(int $value, int $total): float
    {
        return $total > 0 ? round(($value / $total) * 100, 2) : 0;
    }

    /**
     * Get change percentage between current and previous periods
     */
    private function getChangePercentage(int $current, int $previous): float
    {
        if ($previous === 0) {
            return $current > 0 ? 100 : 0;
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }

    /**
     * Get overall completion rate
     */
    private function getCompletionRate(?Carbon $fromDate = null): float
    {
        $query = CSAgentPropertyAssign::query();

        if ($fromDate) {
            $query->where('assigned_at', '>=', $fromDate);
        }

        $total = $query->count();
        $completed = $query->where('status', 'completed')->count();

        return $total > 0 ? round(($completed / $total) * 100, 2) : 0;
    }

    /**
     * Get average completion time in hours
     */
    private function getAverageCompletionTime(?Carbon $fromDate = null): ?float
    {
        $query = CSAgentPropertyAssign::completed()
            ->whereNotNull('started_at')
            ->whereNotNull('completed_at');

        if ($fromDate) {
            $query->where('assigned_at', '>=', $fromDate);
        }

        $assignments = $query->get();

        if ($assignments->isEmpty()) {
            return null;
        }

        $totalHours = $assignments->sum(function ($assignment) {
            return $assignment->started_at->diffInHours($assignment->completed_at);
        });

        return round($totalHours / $assignments->count(), 2);
    }

    /**
     * Get status color for UI
     */
    private function getStatusColor(string $status): string
    {
        return match($status) {
            'pending' => '#f59e0b',      // amber
            'in_progress' => '#3b82f6',  // blue
            'completed' => '#10b981',    // green
            'rejected' => '#ef4444',     // red
            default => '#6b7280'         // gray
        };
    }

    /**
     * Get workload status based on active assignments count
     */
    private function getWorkloadStatus(int $activeAssignments): string
    {
        return match(true) {
            $activeAssignments >= 15 => 'overloaded',
            $activeAssignments >= 10 => 'high',
            $activeAssignments >= 5 => 'medium',
            $activeAssignments > 0 => 'low',
            default => 'available'
        };
    }

    /**
     * Get CS Agent assignments chart data
     */
    public function getAssignmentsChart(Request $request): JsonResponse
    {
        try {
            $period = $request->input('period', '30days'); // 7days, 30days, 90days, 1year
            $startDate = $this->getStartDateForPeriod($period);

            $chartData = CSAgentPropertyAssign::selectRaw('
                    DATE(assigned_at) as date,
                    status,
                    COUNT(*) as count
                ')
                ->where('assigned_at', '>=', $startDate)
                ->groupBy('date', 'status')
                ->orderBy('date')
                ->get();

            // Format data for chart
            $formattedData = [];
            $dates = [];

            // Get all dates in range
            $current = Carbon::parse($startDate);
            $end = now();

            while ($current->lte($end)) {
                $dates[] = $current->format('Y-m-d');
                $current->addDay();
            }

            // Initialize data structure
            $statuses = CSAgentPropertyAssign::getStatuses();
            foreach ($dates as $date) {
                $formattedData[$date] = [
                    'date' => $date,
                    'formatted_date' => Carbon::parse($date)->format('M j'),
                ];

                foreach ($statuses as $status) {
                    $formattedData[$date][$status] = 0;
                }
            }

            // Fill with actual data
            foreach ($chartData as $data) {
                if (isset($formattedData[$data->date])) {
                    $formattedData[$data->date][$data->status] = $data->count;
                }
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'chart_data' => array_values($formattedData),
                    'period' => $period,
                    'date_range' => [
                        'start' => $startDate->toISOString(),
                        'end' => now()->toISOString(),
                    ],
                    'statuses' => $statuses,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch assignments chart data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get agent performance chart data
     */
    public function getAgentPerformanceChart(Request $request): JsonResponse
    {
        try {
            $period = $request->input('period', '30days');
            $startDate = $this->getStartDateForPeriod($period);

            $performanceData = User::csAgents()
                ->select('id', 'first_name', 'last_name', 'email')
                ->withCount([
                    'csAgentAssignments as total_assignments' => function ($query) use ($startDate) {
                        $query->where('assigned_at', '>=', $startDate);
                    },
                    'csAgentAssignments as completed_assignments' => function ($query) use ($startDate) {
                        $query->where('status', 'completed')
                              ->where('assigned_at', '>=', $startDate);
                    },
                ])
                ->having('total_assignments', '>', 0)
                ->get()
                ->map(function ($agent) {
                    $completionRate = $agent->total_assignments > 0
                        ? round(($agent->completed_assignments / $agent->total_assignments) * 100, 2)
                        : 0;

                    return [
                        'agent_name' => $agent->full_name,
                        'total_assignments' => $agent->total_assignments,
                        'completed_assignments' => $agent->completed_assignments,
                        'completion_rate' => $completionRate,
                    ];
                })
                ->sortByDesc('completion_rate')
                ->take(10) // Top 10 agents
                ->values();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'performance_data' => $performanceData,
                    'period' => $period,
                    'date_range' => [
                        'start' => $startDate->toISOString(),
                        'end' => now()->toISOString(),
                    ],
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch agent performance chart data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get workload distribution chart data
     */
    public function getWorkloadChart(Request $request): JsonResponse
    {
        try {
            $workloadData = User::csAgents()
                ->select('id', 'first_name', 'last_name')
                ->withCount([
                    'csAgentAssignments as active_assignments' => function ($query) {
                        $query->whereIn('status', ['pending', 'in_progress']);
                    }
                ])
                ->get()
                ->groupBy(function ($agent) {
                    return $this->getWorkloadStatus($agent->active_assignments);
                })
                ->map(function ($group, $status) {
                    return [
                        'status' => $status,
                        'count' => $group->count(),
                        'label' => ucfirst($status),
                        'agents' => $group->map(function ($agent) {
                            return [
                                'id' => $agent->id,
                                'name' => $agent->full_name,
                                'active_assignments' => $agent->active_assignments,
                            ];
                        })->values(),
                    ];
                })
                ->values();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'workload_distribution' => $workloadData,
                    'total_agents' => User::csAgents()->count(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch workload chart data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get start date for specified period
     */
    private function getStartDateForPeriod(string $period): Carbon
    {
        return match($period) {
            '7days' => now()->subDays(7),
            '30days' => now()->subDays(30),
            '90days' => now()->subDays(90),
            '1year' => now()->subYear(),
            default => now()->subDays(30)
        };
    }

    /**
     * Get assignments requiring attention
     */
    public function getAssignmentsRequiringAttention(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['type', 'limit']);
            $limit = $filters['limit'] ?? 20;

            $requiresAttention = [
                'overdue' => [],
                'urgent' => [],
                'stale' => [],
                'unassigned' => [],
            ];

            // Get overdue assignments
            if (!isset($filters['type']) || $filters['type'] === 'overdue') {
                $requiresAttention['overdue'] = CSAgentPropertyAssign::pending()
                    ->where('assigned_at', '<=', now()->subDays(7))
                    ->with(['property:id,title,type', 'csAgent:id,first_name,last_name'])
                    ->limit($limit)
                    ->get()
                    ->map(function ($assignment) {
                        return [
                            'id' => $assignment->id,
                            'type' => 'overdue',
                            'property' => [
                                'id' => $assignment->property->id,
                                'title' => $assignment->property->title,
                                'type' => $assignment->property->type,
                            ],
                            'agent' => [
                                'id' => $assignment->csAgent->id,
                                'name' => $assignment->csAgent->full_name,
                            ],
                            'days_overdue' => now()->diffInDays($assignment->assigned_at),
                            'priority' => $assignment->priority,
                            'assigned_at' => $assignment->assigned_at->toISOString(),
                        ];
                    });
            }

            // Get urgent assignments
            if (!isset($filters['type']) || $filters['type'] === 'urgent') {
                $requiresAttention['urgent'] = CSAgentPropertyAssign::whereIn('status', ['pending', 'in_progress'])
                    ->whereJsonContains('metadata->priority', 'urgent')
                    ->with(['property:id,title,type', 'csAgent:id,first_name,last_name'])
                    ->limit($limit)
                    ->get()
                    ->map(function ($assignment) {
                        return [
                            'id' => $assignment->id,
                            'type' => 'urgent',
                            'property' => [
                                'id' => $assignment->property->id,
                                'title' => $assignment->property->title,
                                'type' => $assignment->property->type,
                            ],
                            'agent' => [
                                'id' => $assignment->csAgent->id,
                                'name' => $assignment->csAgent->full_name,
                            ],
                            'status' => $assignment->status,
                            'priority' => $assignment->priority,
                            'assigned_at' => $assignment->assigned_at->toISOString(),
                        ];
                    });
            }

            // Get stale in-progress assignments
            if (!isset($filters['type']) || $filters['type'] === 'stale') {
                $requiresAttention['stale'] = CSAgentPropertyAssign::inProgress()
                    ->where('started_at', '<=', now()->subDays(3))
                    ->with(['property:id,title,type', 'csAgent:id,first_name,last_name'])
                    ->limit($limit)
                    ->get()
                    ->map(function ($assignment) {
                        return [
                            'id' => $assignment->id,
                            'type' => 'stale',
                            'property' => [
                                'id' => $assignment->property->id,
                                'title' => $assignment->property->title,
                                'type' => $assignment->property->type,
                            ],
                            'agent' => [
                                'id' => $assignment->csAgent->id,
                                'name' => $assignment->csAgent->full_name,
                            ],
                            'days_in_progress' => now()->diffInDays($assignment->started_at),
                            'started_at' => $assignment->started_at->toISOString(),
                        ];
                    });
            }

            // Get unassigned properties
            if (!isset($filters['type']) || $filters['type'] === 'unassigned') {
                $requiresAttention['unassigned'] = Property::unassigned()
                    ->where('property_state', 'Pending')
                    ->with(['owner:id,first_name,last_name'])
                    ->limit($limit)
                    ->get()
                    ->map(function ($property) {
                        return [
                            'id' => $property->id,
                            'type' => 'unassigned',
                            'property' => [
                                'id' => $property->id,
                                'title' => $property->title,
                                'type' => $property->type,
                            ],
                            'owner' => [
                                'id' => $property->owner->id,
                                'name' => $property->owner->full_name,
                            ],
                            'days_pending' => now()->diffInDays($property->created_at),
                            'created_at' => $property->created_at->toISOString(),
                        ];
                    });
            }

            return response()->json([
                'status' => 'success',
                'data' => $requiresAttention,
                'summary' => [
                    'overdue_count' => count($requiresAttention['overdue']),
                    'urgent_count' => count($requiresAttention['urgent']),
                    'stale_count' => count($requiresAttention['stale']),
                    'unassigned_count' => count($requiresAttention['unassigned']),
                    'total_count' => array_sum([
                        count($requiresAttention['overdue']),
                        count($requiresAttention['urgent']),
                        count($requiresAttention['stale']),
                        count($requiresAttention['unassigned']),
                    ]),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch assignments requiring attention',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
