<?php

// config/admin.php

return [
    /*
    |--------------------------------------------------------------------------
    | Admin Dashboard Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options for the admin dashboard functionality
    |
    */

    'dashboard' => [
        'cache_ttl' => env('ADMIN_DASHBOARD_CACHE_TTL', 60), // 1 minute (reduced from 5 minutes)
        'stats_cache_ttl' => env('ADMIN_STATS_CACHE_TTL', 60), // 1 minute for dashboard stats
        'chart_cache_ttl' => env('ADMIN_CHART_CACHE_TTL', 120), // 2 minutes for chart data
        'property_stats_cache_ttl' => env('ADMIN_PROPERTY_STATS_CACHE_TTL', 60), // 1 minute for property statistics
        'filters_cache_ttl' => env('ADMIN_FILTERS_CACHE_TTL', 1800), // 30 minutes for filters (less frequent changes)
        'stats_refresh_interval' => env('ADMIN_STATS_REFRESH_INTERVAL', 60),
        'chart_data_limit' => env('ADMIN_CHART_DATA_LIMIT', 12), // months
    ],

    'audit' => [
        'enabled' => env('ADMIN_AUDIT_ENABLED', true),
        'retention_days' => env('AUDIT_LOG_RETENTION_DAYS', 365),
    ],

    'pagination' => [
        'default_per_page' => env('ADMIN_DEFAULT_PER_PAGE', 15),
        'max_per_page' => env('ADMIN_MAX_PER_PAGE', 100),
    ],

    'permissions' => [
        'dashboard' => [
            'view_stats',
            'view_charts',
            'export_data',
        ],
        'users' => [
            'view_users',
            'edit_users',
            'delete_users',
            'manage_roles',
        ],
        'properties' => [
            'view_properties',
            'approve_properties',
            'reject_properties',
            'assign_cs_agent',
        ],
        'transactions' => [
            'view_transactions',
            'process_refunds',
            'export_reports',
        ],
    ],
];
