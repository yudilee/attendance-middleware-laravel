<?php

namespace App\Console\Commands;

use App\Services\AdmsService;
use Illuminate\Console\Command;

class AdmsHeartbeatCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'adms:heartbeat {--sn=VIRTUAL_MOBILE_01 : Device Serial Number}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send periodic heartbeat/ping to ADMS server to maintain Online status';

    /**
     * Execute the console command.
     */
    public function handle(AdmsService $admsService): int
    {
        $sn = $this->option('sn') ?: 'VIRTUAL_MOBILE_01';
        $this->info("Sending ADMS heartbeat for SN: {$sn}...");

        $result = $admsService->sendHeartbeat($sn);

        if ($result['success']) {
            $this->info("✓ " . $result['message']);
            return Command::SUCCESS;
        }

        $this->error("✗ " . $result['message']);
        return Command::FAILURE;
    }
}
