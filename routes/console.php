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

Schedule::command('meetings:check-upcoming')->everyMinute()->withoutOverlapping(10);
Schedule::command('meetings:auto-join')->everyMinute()->withoutOverlapping(10);
Schedule::command('meetings:cleanup')->daily()->at('04:00')->withoutOverlapping(60);

Schedule::command('onesibox:prune-screenshots')
    ->everyFiveMinutes()
    ->withoutOverlapping(5)
    ->runInBackground();

Schedule::command('onesibox:prune-screenshots --sweep-orphans')
    ->dailyAt('03:15')
    ->withoutOverlapping();
