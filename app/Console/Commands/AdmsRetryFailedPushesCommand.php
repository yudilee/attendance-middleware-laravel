<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AdmsService;

class AdmsRetryFailedPushesCommand extends Command
{
    protected $signature = 'adms:retry-failed-pushes';
    protected $description = 'Retry failed/pending punch pushes to ADMS';

    public function handle(AdmsService $admsService)
    {
        $this->info('Retrying failed/pending ADMS pushes...');

        try {
            $result = $admsService->syncPendingPunches();
            $this->info($result['message'] ?? 'Retry completed');
            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to retry pushes: ' . $e->getMessage());
            return 1;
        }
    }
}
