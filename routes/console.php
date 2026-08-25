<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
// ADMS Heartbeat to keep virtual attendance device Online
Schedule::command('adms:heartbeat --sn=VIRTUAL_MOBILE_01')->everyMinute()->withoutOverlapping();

// ADMS Employee Sync — automatically sync employees from ADMS every 24 hours
Schedule::command('adms:sync-employees')
    ->daily()
    ->withoutOverlapping()
    ->description('Automatically sync employees from ADMS every 24 hours');
// ADMS Retry Failed Pushes — retry pending/failed punch pushes every 5 minutes
Schedule::command('adms:retry-failed-pushes')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->description('Retry failed ADMS punch pushes every 5 minutes');

// Fix Duplicate Employees — cleanup command (run manually with --dry-run first)
// Usage: php artisan adms:fix-duplicates --dry-run   (preview)
// Usage: php artisan adms:fix-duplicates --force      (apply cleanup)
// NOTE: Command is auto-discovered in app/Console/Commands/ (Laravel 11)

