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
        'cache_ttl' => env('ADMIN_DASHBOARD_CACHE_TTL', 300), // 5 minutes
        'stats_refresh_interval' => env('ADMIN_STATS_REFRESH_INTERVAL', 300),
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
