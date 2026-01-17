<?php

declare(strict_types=1);

namespace App\Traits;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

trait LogsActivityAllDirty
{
    use LogsActivity;

    public static string $activityLogName = 'crud';

    /**
     * Specifies which model properties should be logged
     * in the activy log
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()->logOnlyDirty()->useLogName(self::$activityLogName);
    }
}
