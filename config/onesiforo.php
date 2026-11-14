<?php

declare(strict_types=1);

return [
    'onesibox_min_version' => env('ONESIBOX_MIN_VERSION', '1.0.0'),

    /*
    |--------------------------------------------------------------------------
    | Retention of collected OnesiBox log snapshots
    |--------------------------------------------------------------------------
    |
    | Snapshots are captured periodically by `onesibox:collect-logs` and pruned
    | by `onesibox:prune-log-snapshots`. Age and per-box count are enforced
    | independently, whichever bites first.
    |
    */
    'log_snapshots' => [
        'retention_days' => (int) env('ONESIBOX_LOG_RETENTION_DAYS', 14),
        'max_per_box' => (int) env('ONESIBOX_LOG_MAX_PER_BOX', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Archiving of the activity log
    |--------------------------------------------------------------------------
    |
    | `activity-log:archive` runs once a day: entries older than the retention
    | window are written to a CSV file on the given disk and only then removed
    | from the database. Nothing is deleted if the file cannot be written.
    |
    */
    'activity_log' => [
        'archive_enabled' => (bool) env('ACTIVITY_LOG_ARCHIVE_ENABLED', true),
        'retention_months' => (int) env('ACTIVITY_LOG_RETENTION_MONTHS', 6),
        'archive_disk' => env('ACTIVITY_LOG_ARCHIVE_DISK', 'local'),
        'archive_path' => env('ACTIVITY_LOG_ARCHIVE_PATH', 'activity-log-archive'),
        'archive_time' => env('ACTIVITY_LOG_ARCHIVE_TIME', '03:45'),
    ],
];
