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
];
