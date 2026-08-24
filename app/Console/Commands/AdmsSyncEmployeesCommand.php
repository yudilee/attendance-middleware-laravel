<?php

namespace App\Console\Commands;

use App\Services\AdmsService;
use App\Models\AppConfig;
use Illuminate\Console\Command;

class AdmsSyncEmployeesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'adms:sync-employees {--force : Force sync even if auto-sync is disabled}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync employees from ADMS server automatically';

    /**
     * Execute the console command.
     */
    public function handle(AdmsService $admsService): int
    {
        if (!$this->option('force')) {
            $enabled = AppConfig::where('key', 'adms_auto_sync_enabled')->value('value');
            if ($enabled !== 'true') {
                $this->warn('Auto ADMS employee sync is disabled. Use --force to override.');
                return 1;
            }
        }

        $this->info('Starting ADMS employee sync...');

        try {
            $result = $admsService->syncEmployees();

            AppConfig::updateOrCreate(
                ['key' => 'last_adms_sync_at'],
                ['value' => now()->toIso8601String(), 'description' => 'Last ADMS employee sync timestamp']
            );

            if ($result['success']) {
                $this->info($result['message']);
                return Command::SUCCESS;
            }

            $this->error($result['message']);
            return Command::FAILURE;
        } catch (\Exception $e) {
            $this->error('ADMS sync failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
