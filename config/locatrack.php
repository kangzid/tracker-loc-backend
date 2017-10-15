<?php

return [
    /*
    |--------------------------------------------------------------------------
    | LocaTrack Application Settings
    |--------------------------------------------------------------------------
    */
    'app_name' => env('APP_NAME', 'LocaTrack'),
    'app_version' => '1.0.0',

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'geofence_ttl' => env('CACHE_GEOFENCE_TTL', 3600), // 1 hour
        'attendance_today_ttl' => env('CACHE_ATTENDANCE_TODAY_TTL', 300), // 5 minutes
        'dashboard_stats_ttl' => env('CACHE_DASHBOARD_STATS_TTL', 600), // 10 minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Location Settings
    |--------------------------------------------------------------------------
    */
    'location' => [
        'share_max_duration' => env('LOCATION_SHARE_MAX_DURATION', 1440), // 24 hours in minutes
        'history_pagination' => env('LOCATION_HISTORY_PAGINATION', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Task Settings
    |--------------------------------------------------------------------------
    */
    'task' => [
        'priorities' => ['low', 'medium', 'high', 'urgent'],
        'statuses' => ['pending', 'accepted', 'in_progress', 'completed', 'cancelled'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Vehicle Settings
    |--------------------------------------------------------------------------
    */
    'vehicle' => [
        'token_length' => env('VEHICLE_TOKEN_LENGTH', 32),
    ],

    /*
    |--------------------------------------------------------------------------
    | Company Registration Settings
    |--------------------------------------------------------------------------
    */
    'registration' => [
        'auto_approve' => env('REGISTRATION_AUTO_APPROVE', false),
        'default_password_length' => env('REGISTRATION_PASSWORD_LENGTH', 12),
        'require_phone_verification' => env('REGISTRATION_REQUIRE_PHONE', false),
        'pending_expiry_days' => env('REGISTRATION_PENDING_EXPIRY_DAYS', 7),
    ],
];
