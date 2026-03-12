<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:expire-sessions')->everyMinute()->withoutOverlapping();
Schedule::command('app:prune-playback-events')->daily()->at('03:00');

Schedule::command('meetings:check-upcoming')->everyMinute()->withoutOverlapping();
Schedule::command('meetings:auto-join')->everyMinute()->withoutOverlapping();
Schedule::command('meetings:cleanup')->daily()->at('04:00');
