<?php

namespace App\Console\Commands;

use App\Models\Employee;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanupCorruptedEmployeesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'adms:cleanup-corrupted
                            {--dry-run : Preview corrupted records without deleting}
                            {--force : Actually delete corrupted records}';

    protected $aliases = ['cleanup:corrupted-employees'];

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove employee records corrupted by wrong ADMS column mapping or empty PINs';

    /**
     * Execute the console command.
     *
     * After the ADMS column mapping fix, records that were synced with the wrong
     * mapping have employee_id containing a name (e.g. "Agus Apri") instead of
     * a numeric PIN, or empty/whitespace PINs with department names.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        if (!$dryRun && !$force) {
            $this->warn('Use --dry-run to preview or --force to execute cleanup.');
            return Command::INVALID;
        }

        // ── Identify corrupted records ──
        $corrupted = Employee::where(function ($q) {
            $driver = DB::connection()->getDriverName();

            // 1. Non-numeric employee_id (names stored as PIN)
            $q->where(function ($sub) use ($driver) {
                if ($driver === 'pgsql') {
                    $sub->whereRaw('employee_id ~ \'[^0-9]\'');
                } elseif ($driver === 'mysql' || $driver === 'mariadb') {
                    $sub->whereRaw('employee_id REGEXP \'[^0-9]\'');
                } elseif ($driver === 'sqlite') {
                    $sub->whereRaw("employee_id GLOB '*[^0-9]*'");
                }
            });

            // 2. Empty, whitespace, or invalid placeholder employee_id
            $q->orWhereNull('employee_id')
              ->orWhere('employee_id', '')
              ->orWhereRaw("TRIM(employee_id) = ''")
              ->orWhereIn('employee_id', ['None', '-', '--', 'undefined', 'n/a'])
              ->orWhere(function ($sub) {
                  $sub->where('full_name', '1 Default Dept')
                      ->where(function ($sub2) {
                          $sub2->whereNull('employee_id')
                               ->orWhereRaw("TRIM(employee_id) = ''")
                               ->orWhere('department', 'None');
                      });
              });
        })
        ->get();

        $totalCount = $corrupted->count();
        $this->info("Found {$totalCount} corrupted employee records.");

        if ($totalCount === 0) {
            $this->info('No corrupted records found. The database is clean.');
            return Command::SUCCESS;
        }

        // ── Display preview ──
        $this->newLine();
        $this->warn('=== Corrupted Employee Records ===');
        $this->table(
            ['ID', 'employee_id (should be PIN)', 'full_name', 'department', 'employee_type'],
            $corrupted->map(fn ($e) => [
                $e->id,
                $e->employee_id ?? '(null)',
                $e->full_name ?? '(null)',
                $e->department ?? '(null)',
                $e->employee_type ?? '(null)',
            ])
        );

        $this->newLine();
        $this->line(sprintf('Records with non-numeric employee_id: %d', $corrupted->count()));
        $this->newLine();

        if ($dryRun) {
            $this->warn('This was a dry run — no records were deleted.');
            $this->info('Run with --force to delete these corrupted records.');
            $this->newLine();
            $this->info('Recommended workflow:');
            $this->info('  1. First run: php artisan adms:sync-employees --force');
            $this->info('     (This will create correct records using the fixed column mapping)');
            $this->info('  2. Then run: php artisan adms:cleanup-corrupted --force');
            $this->info('     (This will remove the old corrupted records)');

            return Command::SUCCESS;
        }

        if ($force) {
            // Collect IDs for logging
            $corruptedIds = $corrupted->pluck('id')->toArray();
            $deletedCount = Employee::whereIn('id', $corruptedIds)->delete();

            $this->info("Deleted {$deletedCount} corrupted employee records.");
            Log::info('Cleaned up corrupted employee records from wrong ADMS mapping', [
                'deleted_count' => $deletedCount,
                'corrupted_employee_ids' => $corrupted->pluck('employee_id')->toArray(),
            ]);

            return Command::SUCCESS;
        }

        return Command::INVALID;
    }
}
