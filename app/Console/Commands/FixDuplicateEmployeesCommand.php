<?php

namespace App\Console\Commands;

use App\Models\Employee;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FixDuplicateEmployeesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'adms:fix-duplicates
                            {--dry-run : Preview duplicates without making changes}
                            {--force : Actually remove duplicate records}';

    protected $aliases = ['fix:duplicate-employees'];

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find and fix employees with duplicate or empty employee_id';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        if (!$dryRun && !$force) {
            $this->warn('Use --dry-run to preview or --force to execute cleanup.');
            return Command::INVALID;
        }

        $issues = [];

        // ── 1. Find employees with empty/null/whitespace employee_id or placeholder records ──
        $emptyIdEmployees = Employee::whereNull('employee_id')
            ->orWhere('employee_id', '')
            ->orWhereRaw("TRIM(employee_id) = ''")
            ->orWhere('employee_id', 'None')
            ->orWhere('employee_id', '-')
            ->orWhere(function ($q) {
                $q->where('full_name', '1 Default Dept')
                  ->where(function ($sub) {
                      $sub->whereNull('employee_id')
                          ->orWhereRaw("TRIM(employee_id) = ''")
                          ->orWhere('employee_id', 'None');
                  });
            })
            ->get();

        if ($emptyIdEmployees->isNotEmpty()) {
            $issues['empty_employee_id'] = [
                'description' => 'Employees with empty employee_id',
                'records' => $emptyIdEmployees,
            ];
        }

        // ── 2. Find employees with duplicate employee_id (non-empty) ──
        $duplicates = Employee::select('employee_id')
            ->whereNotNull('employee_id')
            ->where('employee_id', '!=', '')
            ->groupBy('employee_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('employee_id');

        $duplicateRecords = [];
        foreach ($duplicates as $dupId) {
            $records = Employee::where('employee_id', $dupId)
                ->orderBy('id', 'asc')  // oldest first
                ->get();
            $duplicateRecords[$dupId] = $records;
        }

        if (!empty($duplicateRecords)) {
            $issues['duplicate_employee_id'] = [
                'description' => 'Employees with duplicate employee_id',
                'records' => $duplicateRecords,
            ];
        }

        // ── Report ──
        if (empty($issues)) {
            $this->info('✓ No duplicate or empty employee_id issues found.');
            return Command::SUCCESS;
        }

        $this->info('=== Duplicate Employee Report ===');
        $this->newLine();

        // Report empty employee_id
        if (isset($issues['empty_employee_id'])) {
            $emptyRecords = $issues['empty_employee_id']['records'];
            $this->warn(sprintf(
                'Found %d employee(s) with empty employee_id:',
                $emptyRecords->count()
            ));
            $this->table(
                ['ID', 'Full Name', 'Department', 'Employee Type', 'Is Active', 'Is Deleted'],
                $emptyRecords->map(fn($e) => [
                    $e->id,
                    $e->full_name ?? '(no name)',
                    $e->department ?? '(none)',
                    $e->employee_type ?? '(none)',
                    $e->is_active ? 'Yes' : 'No',
                    $e->is_deleted ? 'Yes' : 'No',
                ])
            );

            if ($force) {
                foreach ($emptyRecords as $emp) {
                    $emp->delete();
                    $this->line("  Deleted employee ID={$emp->id} (empty employee_id)");
                }
                Log::info('Deleted employees with empty employee_id', [
                    'count' => $emptyRecords->count(),
                ]);
            }
        }

        // Report duplicate employee_id
        if (isset($issues['duplicate_employee_id'])) {
            foreach ($duplicateRecords as $dupId => $records) {
                $this->warn(sprintf(
                    'employee_id "%s" has %d records:',
                    $dupId,
                    $records->count()
                ));
                $this->table(
                    ['ID', 'Full Name', 'Department', 'Employee Type', 'Is Active', 'Is Deleted', 'Created At'],
                    $records->map(fn($e) => [
                        $e->id,
                        $e->full_name ?? '(no name)',
                        $e->department ?? '(none)',
                        $e->employee_type ?? '(none)',
                        $e->is_active ? 'Yes' : 'No',
                        $e->is_deleted ? 'Yes' : 'No',
                        $e->created_at ?? '(unknown)',
                    ])
                );

                if ($force) {
                    // Keep the oldest record (first in orderBy id asc), delete the rest
                    $keep = $records->shift(); // removes first (oldest) from collection
                    $this->line(sprintf(
                        '  Keeping ID=%d (%s) as the canonical record',
                        $keep->id,
                        $keep->full_name ?? 'no name'
                    ));

                    foreach ($records as $dup) {
                        $dup->delete();
                        $this->line(sprintf(
                            '  Deleted duplicate ID=%d (%s)',
                            $dup->id,
                            $dup->full_name ?? 'no name'
                        ));
                    }

                    Log::info('Removed duplicate employees', [
                        'employee_id' => $dupId,
                        'kept_id' => $keep->id,
                        'removed_count' => $records->count(),
                    ]);
                }
            }
        }

        // Summary
        if ($dryRun) {
            $this->newLine();
            $this->info('This was a dry run — no changes were made.');
            $this->info('Run with --force to apply the cleanup.');
        } elseif ($force) {
            $this->newLine();
            $this->info('✓ Cleanup completed.');
        }

        return Command::SUCCESS;
    }
}
