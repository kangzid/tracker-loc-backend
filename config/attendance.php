<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Work Hours Configuration
    |--------------------------------------------------------------------------
    */
    'work_start_time' => env('WORK_START_TIME', '08:00'),
    'work_end_time' => env('WORK_END_TIME', '17:00'),
    'late_threshold_minutes' => env('LATE_THRESHOLD_MINUTES', 30), // 08:30
    'early_leave_threshold_minutes' => env('EARLY_LEAVE_THRESHOLD_MINUTES', 30), // 16:30

    /*
    |--------------------------------------------------------------------------
    | Attendance Settings
    |--------------------------------------------------------------------------
    */
    'require_geofence' => env('ATTENDANCE_REQUIRE_GEOFENCE', true),
    'allow_edit_days' => env('ATTENDANCE_ALLOW_EDIT_DAYS', 7), // Admin can edit within 7 days
    'allow_delete_days' => env('ATTENDANCE_ALLOW_DELETE_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Status Options
    |--------------------------------------------------------------------------
    */
    'statuses' => [
        'present' => 'Present',
        'absent' => 'Absent',
        'late' => 'Late',
        'early_leave' => 'Early Leave',
        'sick' => 'Sick',
        'leave' => 'Leave',
    ],
];
