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

