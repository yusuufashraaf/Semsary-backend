<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Laravel CORS Configuration
    |--------------------------------------------------------------------------
    */

    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
        'broadcasting/auth',
    ],

    // Allow all HTTP methods
    'allowed_methods' => ['*'],

    // Explicitly allow local dev & your production domain
    'allowed_origins' => [
        'http://localhost:3000',
        'http://127.0.0.1:3000',
        'http://localhost:5173',
        'http://127.0.0.1:5173',
                'https://semsary-v1.netlify.app',
    ],

    // Regex: allow any Vercel deployment (preview or production)
    'allowed_origins_patterns' => [
        '/^https:\/\/([a-z0-9-]+)\.vercel\.app$/',
                '/^https:\/\/([a-z0-9-]+)\.netlify\.app$/', // allow Netlify subdomains
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 3600,

    'supports_credentials' => true,
];