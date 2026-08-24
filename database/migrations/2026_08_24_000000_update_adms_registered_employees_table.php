<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('adms_registered_employees', function (Blueprint $table) {
            // Add sync_status enum column if not exists
            if (!Schema::hasColumn('adms_registered_employees', 'sync_status')) {
                $table->enum('sync_status', ['pending', 'registered', 'failed'])
                    ->default('pending')
                    ->after('employee_name');
            }

            // Add error_message text column if not exists
            if (!Schema::hasColumn('adms_registered_employees', 'error_message')) {
                $table->text('error_message')->nullable()->after('sync_status');
            }

            // Add last_synced_at timestamp column if not exists
            if (!Schema::hasColumn('adms_registered_employees', 'last_synced_at')) {
                $table->timestamp('last_synced_at')->nullable()->after('error_message');
            }

            // Add timestamps (created_at, updated_at) if not exists
            if (!Schema::hasColumn('adms_registered_employees', 'created_at')) {
                $table->timestamps();
            }
        });

        // Add foreign key from employee_id to employees.employee_id if not already present.
        // SQLite does not support ALTER TABLE ADD FOREIGN KEY (requires table recreation),
        // so we skip FK addition on SQLite. On MySQL/PostgreSQL it works fine.
        $driver = DB::connection()->getDriverName();
        if ($driver !== 'sqlite') {
            Schema::table('adms_registered_employees', function (Blueprint $table) {
                $fkExists = false;
                try {
                    $sm = Schema::getConnection()->getDoctrineSchemaManager();
                    $foreignKeys = $sm->listTableForeignKeys('adms_registered_employees');
                    foreach ($foreignKeys as $fk) {
                        if (in_array('employee_id', $fk->getLocalColumns())) {
                            $fkExists = true;
                            break;
                        }
                    }
                } catch (\Throwable $e) {
                    // Doctrine not available, skip FK check
                }

                if (!$fkExists) {
                    $table->foreign('employee_id')
                        ->references('employee_id')
                        ->on('employees')
                        ->cascadeOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('adms_registered_employees', function (Blueprint $table) {
            // Drop foreign key if exists
            try {
                $table->dropForeign(['employee_id']);
            } catch (\Throwable $e) {
                // Foreign key may not exist
            }

            if (Schema::hasColumn('adms_registered_employees', 'created_at')) {
                $table->dropTimestamps();
            }
            if (Schema::hasColumn('adms_registered_employees', 'last_synced_at')) {
                $table->dropColumn('last_synced_at');
            }
            if (Schema::hasColumn('adms_registered_employees', 'error_message')) {
                $table->dropColumn('error_message');
            }
            if (Schema::hasColumn('adms_registered_employees', 'sync_status')) {
                $table->dropColumn('sync_status');
            }
        });
    }
};
