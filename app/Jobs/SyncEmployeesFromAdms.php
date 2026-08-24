<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Services\AdmsService;

class SyncEmployeesFromAdms implements ShouldQueue
{
    use Queueable;

    public function __construct() {}

    public function handle(AdmsService $admsService): void
    {
        $admsService->syncEmployees();
    }
}
