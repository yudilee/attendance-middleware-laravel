<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ADMS Heartbeat to keep virtual attendance device Online
Schedule::command('adms:heartbeat --sn=VIRTUAL_MOBILE_01')->everyMinute()->withoutOverlapping();


