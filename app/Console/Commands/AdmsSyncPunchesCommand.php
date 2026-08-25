<?php

namespace App\Console\Commands;

use App\Services\AdmsService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AdmsSyncPunchesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'adms:sync-punches
                            {--days=2 : Number of days back to fetch punches for}
                            {--start= : Optional start date (YYYY-MM-DD)}
                            {--end= : Optional end date (YYYY-MM-DD)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch biometric punch records (ATTLOG) from ADMS server with device and branch mapping';

    /**
     * Execute the console command.
     */
    public function handle(AdmsService $admsService): int
    {
        $days = (int) $this->option('days');
        $start = $this->option('start');
        $end = $this->option('end');

        if (!$start) {
            $start = Carbon::today('Asia/Jakarta')->subDays(max(1, $days))->format('Y-m-d');
        }
        if (!$end) {
            $end = Carbon::today('Asia/Jakarta')->addDay()->format('Y-m-d');
        }

        $this->info("Fetching punch logs from ADMS ({$start} to {$end})...");

        $result = $admsService->syncPunchesFromAdms($start, $end);

        if ($result['success']) {
            $this->info($result['message']);
            return Command::SUCCESS;
        }

        $this->error($result['message']);
        return Command::FAILURE;
    }
}
