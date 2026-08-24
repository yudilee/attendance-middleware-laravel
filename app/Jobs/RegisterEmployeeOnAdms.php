<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\Employee;
use App\Services\AdmsService;

class RegisterEmployeeOnAdms implements ShouldQueue
{
    use Queueable;

    public Employee $employee;

    public function __construct(Employee $employee)
    {
        $this->employee = $employee;
    }

    public function handle(AdmsService $admsService): void
    {
        $admsService->registerEmployeeOnAdms($this->employee);
    }
}
