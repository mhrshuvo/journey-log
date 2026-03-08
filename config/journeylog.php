<?php

return [
    'storage_path' => env('JOURNEY_LOG_STORAGE_PATH', 'journeys'),
    'header' => env('JOURNEY_LOG_HEADER', 'X-Journey-ID'),
    'session_key' => env('JOURNEY_LOG_SESSION_KEY', 'journey_id'),

    /*
    |--------------------------------------------------------------------------
    | Summary Panel
    |--------------------------------------------------------------------------
    |
    | Enable or disable the admin summary panel that displays journey
    | timelines. You can also customise the route prefix and the
    | middleware applied to those routes.
    |
    */

    'summary_enabled' => env('JOURNEY_LOG_SUMMARY_ENABLED', false),
    'summary_route_prefix' => env('JOURNEY_LOG_SUMMARY_ROUTE_PREFIX', 'journey-log'),
    'summary_middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Automatic Cleanup
    |--------------------------------------------------------------------------
    |
    | Enable automatic cleanup of old journey log files. When enabled,
    | the package will automatically delete JSON files older than the
    | specified retention period and remove empty directories.
    |
    */

    'auto_cleanup_enabled' => env('JOURNEY_LOG_AUTO_CLEANUP_ENABLED', true),
    'cleanup_retention_hours' => env('JOURNEY_LOG_CLEANUP_RETENTION_HOURS', 1),

    /*
    |--------------------------------------------------------------------------
    | Global Masking
    |--------------------------------------------------------------------------
    | Any keys listed here will have their values replaced with '*' in the logs.
    */
    'mask_fields' => [
        'password',
        'password_confirmation',
        'cvv',
        'card_number',
        'api_key',
        'auth_token',
        'access_token',
        'secret',
    ],
];
