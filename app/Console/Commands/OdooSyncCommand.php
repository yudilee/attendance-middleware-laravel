<?php

namespace App\Console\Commands;

use App\Services\OdooSyncService;
use App\Services\OdooService;
use Illuminate\Console\Command;

class OdooSyncCommand extends Command
{
    protected $signature = 'odoo:sync 
        {--type=full : Sync operation type (full|customers_pull|customers_push|visits_push|employees_pull)}';

    protected $description = 'Synchronize data bi-directionally with Odoo CRM / ERP';

    public function handle(OdooService $odoo, OdooSyncService $sync)
    {
        $this->info("Checking Odoo configuration...");

        if (!$odoo->isEnabled()) {
            $this->warn("Odoo sync is currently DISABLED in configuration. Skipping.");
            return 0;
        }

        $type = $this->option('type');
        $this->info("Starting Odoo synchronization (Type: {$type})...");

        switch ($type) {
            case 'customers_pull':
                $log = $sync->pullCustomers();
                $this->outputLog($log);
                break;

            case 'customers_push':
                $log = $sync->pushCustomers();
                $this->outputLog($log);
                break;

            case 'visits_push':
                $log = $sync->pushVisits();
                $this->outputLog($log);
                break;

            case 'employees_pull':
                $log = $sync->pullEmployees();
                $this->outputLog($log);
                break;

            case 'full':
            default:
                $res = $sync->runFullSync();
                if (!$res['success']) {
                    $this->error($res['message']);
                    return 1;
                }
                foreach ($res['results'] as $op => $log) {
                    $this->outputLog($log);
                }
                break;
        }

        $this->info("Odoo sync process finished.");
        return 0;
    }

    protected function outputLog($log)
    {
        $status = $log->status === 'completed' ? '<fg=green>COMPLETED</>' : '<fg=red>FAILED</>';
        $this->line("[{$log->sync_type}] {$status} - Processed: {$log->records_processed}, Created: {$log->records_created}, Updated: {$log->records_updated}, Failed: {$log->records_failed}");
        if ($log->error_message) {
            $this->error("Error: {$log->error_message}");
        }
    }
}
