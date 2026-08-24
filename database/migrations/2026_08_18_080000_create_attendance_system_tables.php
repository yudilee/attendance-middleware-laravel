<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Shift Schedules
        if (!Schema::hasTable('shift_schedules')) {
            Schema::create('shift_schedules', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100);
                $table->string('start_time', 5)->default('08:00');
                $table->string('end_time', 5)->default('17:00');
                $table->integer('grace_minutes')->default(15);
                $table->double('min_work_hours')->default(8.0);
                $table->double('overtime_after_hours')->default(9.0);
                $table->string('working_days', 50)->default('1,2,3,4,5');
                $table->boolean('is_default')->default(false);
                $table->string('schedule_type', 50)->nullable()->default('weekly');
                $table->integer('interval_days')->nullable();
                $table->date('anchor_date')->nullable();
                $table->double('overtime_multiplier_1')->default(1.5);
                $table->double('overtime_multiplier_2')->default(2.0);
                $table->double('overtime_threshold_2_hours')->nullable();
                $table->double('weekend_overtime_multiplier')->default(2.0);
                $table->double('holiday_overtime_multiplier')->default(3.0);
                $table->double('monthly_overtime_cap_hours')->nullable();
                $table->boolean('auto_clockout_enabled')->default(false);
                $table->integer('auto_clockout_buffer_minutes')->default(60);
                $table->timestamp('created_at')->useCurrent();
            });
        }

        // 2. Companies
        if (!Schema::hasTable('companies')) {
            Schema::create('companies', function (Blueprint $table) {
                $table->id();
                $table->string('name', 150);
                $table->string('code', 50)->unique();
                $table->boolean('is_active')->default(true);
                $table->foreignId('shift_schedule_id')->nullable()->constrained('shift_schedules')->nullOnDelete();
                $table->timestamps();
            });
        }

        // 3. Branches
        if (!Schema::hasTable('branches')) {
            Schema::create('branches', function (Blueprint $table) {
                $table->id();
                $table->string('name')->default('Default Office');
                $table->double('latitude')->default(0.0);
                $table->double('longitude')->default(0.0);
                $table->double('radius_meters')->default(100.0);
                $table->boolean('is_active')->default(true);
                $table->string('geofence_type', 20)->default('circle');
                $table->text('polygon_coordinates')->nullable();
                $table->boolean('qr_code_enabled')->default(false);
                $table->string('qr_code_data', 256)->nullable();
                $table->boolean('nfc_enabled')->default(false);
                $table->string('nfc_tag_data', 256)->nullable();
                $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
                $table->foreignId('shift_schedule_id')->nullable()->constrained('shift_schedules')->nullOnDelete();
                $table->integer('timezone_offset')->default(7);
                $table->string('timezone_name', 50)->nullable()->default('Asia/Jakarta');
                $table->timestamp('updated_at')->useCurrent();
            });
        }

        // 4. Employee Groups
        if (!Schema::hasTable('employee_groups')) {
            Schema::create('employee_groups', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100);
                $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
                $table->foreignId('shift_schedule_id')->nullable()->constrained('shift_schedules')->nullOnDelete();
                $table->timestamps();
            });
        }

        // 5. Branch Checkpoints
        if (!Schema::hasTable('branch_checkpoints')) {
            Schema::create('branch_checkpoints', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
                $table->string('name');
                $table->double('latitude');
                $table->double('longitude');
                $table->double('radius_meters')->default(50.0);
                $table->boolean('is_active')->default(true);
                $table->string('geofence_type', 20)->default('circle');
                $table->text('polygon_coordinates')->nullable();
                $table->timestamps();

                $table->index('branch_id', 'idx_checkpoint_branch');
            });
        }

        // 6. Employees
        if (!Schema::hasTable('employees')) {
            Schema::create('employees', function (Blueprint $table) {
                $table->id();
                $table->string('adms_id')->nullable()->index();
                $table->string('employee_id', 50)->unique();
                $table->string('full_name')->nullable();
                $table->string('department')->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('is_deleted')->default(false);
                $table->string('employee_type', 50)->default('regular');
                $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
                $table->foreignId('group_id')->nullable()->constrained('employee_groups')->nullOnDelete();
                $table->foreignId('shift_schedule_id')->nullable()->constrained('shift_schedules')->nullOnDelete();
                $table->timestamp('last_synced')->useCurrent();
            });
        }

        // 7. API Keys
        if (!Schema::hasTable('api_keys')) {
            Schema::create('api_keys', function (Blueprint $table) {
                $table->id();
                $table->string('key_value')->unique();
                $table->string('label')->default('Mobile Client');
                $table->boolean('is_active')->default(true);
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('last_used_at')->nullable();
                $table->string('last_used_ip', 45)->nullable();
                $table->timestamp('expires_at')->nullable();
            });
        }

        // 8. Device Bindings
        if (!Schema::hasTable('device_bindings')) {
            Schema::create('device_bindings', function (Blueprint $table) {
                $table->id();
                $table->string('employee_id', 50)->nullable()->index();
                $table->string('device_uuid')->index();
                $table->integer('branch_id')->nullable();
                $table->foreignId('api_key_id')->nullable()->constrained('api_keys')->nullOnDelete();
                $table->string('device_label')->nullable();
                $table->string('registration_status')->default('pending_approval');
                $table->timestamp('approved_at')->nullable();
                $table->string('approved_by')->nullable();
                $table->string('notes')->nullable();
                $table->boolean('is_active')->default(true);
                $table->string('fcm_token', 500)->nullable();
                $table->string('device_secret', 100)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['employee_id'], 'idx_device_binding_employee');
                $table->index(['device_uuid'], 'idx_device_binding_device');
            });
        }

        // 9. Device Branch Assignments (Pivot)
        if (!Schema::hasTable('device_branch_assignments')) {
            Schema::create('device_branch_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('binding_id')->constrained('device_bindings')->cascadeOnDelete();
                $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
                $table->timestamp('assigned_at')->useCurrent();

                $table->unique(['binding_id', 'branch_id']);
            });
        }

        // 10. Punch Types
        if (!Schema::hasTable('punch_types')) {
            Schema::create('punch_types', function (Blueprint $table) {
                $table->id();
                $table->string('code', 50)->unique();
                $table->string('label');
                $table->string('adms_status_code')->default('0');
                $table->boolean('is_active')->default(true);
                $table->integer('display_order')->default(0);
                $table->string('icon')->nullable();
                $table->string('color_hex')->nullable();
                $table->boolean('requires_geofence')->default(true);
                $table->timestamp('created_at')->useCurrent();
            });
        }

        // 11. Punch Logs
        if (!Schema::hasTable('punch_logs')) {
            Schema::create('punch_logs', function (Blueprint $table) {
                $table->id();
                $table->string('employee_id', 50)->index();
                $table->string('device_uuid')->nullable();
                $table->timestamp('timestamp')->useCurrent();
                $table->double('latitude')->nullable();
                $table->double('longitude')->nullable();
                $table->boolean('is_mock_location')->default(false);
                $table->boolean('biometric_verified')->default(false);
                $table->string('punch_type');
                $table->integer('tz_offset_minutes')->default(420);
                $table->string('adms_status')->default('pending');
                $table->string('client_punch_id')->nullable()->unique();
                $table->boolean('gps_time_validated')->default(false);
                $table->string('notes')->nullable();
                $table->string('selfie_filename', 500)->nullable();
                $table->string('server_sync_status')->default('pending');
                $table->timestamp('synced_at')->nullable();
                $table->string('sync_error', 500)->nullable();
                $table->integer('sync_retry_count')->default(0);
                $table->boolean('is_auto_generated')->default(false);

                $table->index(['employee_id', 'punch_type', 'timestamp'], 'idx_punchlog_employee_type_date');
                $table->index('adms_status', 'idx_punchlog_sync_status');
                $table->index('timestamp', 'idx_punchlog_date');
                $table->index(['employee_id', 'timestamp'], 'idx_punchlog_employee_date');
            });
        }

        // 12. Employee Supervisors
        if (!Schema::hasTable('employee_supervisors')) {
            Schema::create('employee_supervisors', function (Blueprint $table) {
                $table->id();
                $table->string('supervisor_id', 50)->index();
                $table->string('employee_id', 50)->index();
                $table->timestamp('created_at')->useCurrent();

                $table->unique(['supervisor_id', 'employee_id'], 'idx_supervisor_mapping');
            });
        }

        // 13. Holidays
        if (!Schema::hasTable('holidays')) {
            Schema::create('holidays', function (Blueprint $table) {
                $table->id();
                $table->string('name', 200);
                $table->date('date')->unique();
                $table->boolean('is_recurring')->default(false);
                $table->timestamp('created_at')->useCurrent();
            });
        }

        // 14. Leave Requests & Balances
        if (!Schema::hasTable('leave_requests')) {
            Schema::create('leave_requests', function (Blueprint $table) {
                $table->id();
                $table->string('employee_id', 50)->index();
                $table->string('leave_type', 50);
                $table->date('start_date');
                $table->date('end_date');
                $table->string('reason', 500)->nullable();
                $table->string('status', 20)->default('pending');
                $table->string('approved_by', 50)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('employee_id')->references('employee_id')->on('employees')->cascadeOnDelete();
            });
        }

        if (!Schema::hasTable('leave_balances')) {
            Schema::create('leave_balances', function (Blueprint $table) {
                $table->id();
                $table->string('employee_id', 50)->index();
                $table->integer('annual_total')->default(12);
                $table->integer('annual_used')->default(0);
                $table->integer('sick_total')->default(12);
                $table->integer('sick_used')->default(0);
                $table->integer('year');

                $table->foreign('employee_id')->references('employee_id')->on('employees')->cascadeOnDelete();
                $table->unique(['employee_id', 'year'], 'uq_employee_leave_year');
            });
        }

        // 15. Overtime Requests
        if (!Schema::hasTable('overtime_requests')) {
            Schema::create('overtime_requests', function (Blueprint $table) {
                $table->id();
                $table->string('employee_id', 50)->index();
                $table->date('date');
                $table->double('hours_requested');
                $table->string('reason', 500)->nullable();
                $table->string('status', 20)->default('pending');
                $table->string('approved_by', 50)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('employee_id')->references('employee_id')->on('employees')->cascadeOnDelete();
            });
        }

        // 16. Attendance Corrections
        if (!Schema::hasTable('attendance_corrections')) {
            Schema::create('attendance_corrections', function (Blueprint $table) {
                $table->id();
                $table->string('employee_id', 50)->index();
                $table->foreignId('original_punch_id')->nullable()->constrained('punch_logs')->nullOnDelete();
                $table->string('correction_type', 50);
                $table->string('description', 500);
                $table->timestamp('proposed_timestamp')->nullable();
                $table->string('proposed_punch_type', 10)->nullable();
                $table->string('status', 20)->default('pending');
                $table->string('reviewed_by', 50)->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->string('review_notes', 500)->nullable();
                $table->timestamps();
            });
        }

        // 17. Schedule Assignments
        if (!Schema::hasTable('schedule_assignments')) {
            Schema::create('schedule_assignments', function (Blueprint $table) {
                $table->id();
                $table->string('employee_id', 50)->index();
                $table->foreignId('shift_schedule_id')->constrained('shift_schedules')->cascadeOnDelete();
                $table->date('effective_date');
                $table->date('end_date')->nullable();
                $table->string('created_by', 50)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('employee_id')->references('employee_id')->on('employees')->cascadeOnDelete();
                $table->index(['employee_id', 'effective_date'], 'idx_schedule_assignment_lookup');
            });
        }

        // 18. ADMS Targets, Credentials & Registered Employees
        if (!Schema::hasTable('adms_targets')) {
            Schema::create('adms_targets', function (Blueprint $table) {
                $table->id();
                $table->string('server_url')->default('');
                $table->string('serial_number')->default('');
                $table->string('device_name')->default('Mobile Gateway');
                $table->boolean('is_active')->default(true);
                $table->integer('timezone_offset')->default(7);
                $table->timestamp('last_contact')->nullable();
            });
        }

        if (!Schema::hasTable('adms_registered_employees')) {
            Schema::create('adms_registered_employees', function (Blueprint $table) {
                $table->id();
                $table->string('employee_id', 50)->unique();
                $table->string('employee_name')->default('Mobile User');
                $table->timestamp('registered_at')->useCurrent();
            });
        }

        if (!Schema::hasTable('adms_credentials')) {
            Schema::create('adms_credentials', function (Blueprint $table) {
                $table->id();
                $table->string('url')->nullable();
                $table->string('username')->nullable();
                $table->string('password')->nullable();
                $table->boolean('is_active')->default(true);
            });
        }

        // 19. App Configs, Audit Logs & System Error Logs
        if (!Schema::hasTable('app_configs')) {
            Schema::create('app_configs', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->string('description')->nullable();
            });
        }

        if (!Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table) {
                $table->id();
                $table->string('admin_username', 100)->index();
                $table->string('action', 150);
                $table->string('target_type', 50)->nullable();
                $table->string('target_id', 50)->nullable();
                $table->text('details')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        if (!Schema::hasTable('system_error_logs')) {
            Schema::create('system_error_logs', function (Blueprint $table) {
                $table->id();
                $table->string('error_message', 500);
                $table->text('stack_trace')->nullable();
                $table->string('component', 100)->default('laravel');
                $table->timestamp('created_at')->useCurrent();
            });
        }

        // 20. Webhooks & Deliveries
        if (!Schema::hasTable('webhooks')) {
            Schema::create('webhooks', function (Blueprint $table) {
                $table->id();
                $table->string('url', 500);
                $table->string('events', 500)->nullable();
                $table->string('secret', 200)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('created_at')->useCurrent();
            });
        }

        if (!Schema::hasTable('webhook_deliveries')) {
            Schema::create('webhook_deliveries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('webhook_id')->constrained('webhooks')->cascadeOnDelete();
                $table->string('event', 100);
                $table->text('payload');
                $table->integer('response_status')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->string('error', 500)->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhooks');
        Schema::dropIfExists('system_error_logs');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('app_configs');
        Schema::dropIfExists('adms_credentials');
        Schema::dropIfExists('adms_registered_employees');
        Schema::dropIfExists('adms_targets');
        Schema::dropIfExists('schedule_assignments');
        Schema::dropIfExists('attendance_corrections');
        Schema::dropIfExists('overtime_requests');
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('employee_supervisors');
        Schema::dropIfExists('punch_logs');
        Schema::dropIfExists('punch_types');
        Schema::dropIfExists('device_branch_assignments');
        Schema::dropIfExists('device_bindings');
        Schema::dropIfExists('api_keys');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('branch_checkpoints');
        Schema::dropIfExists('employee_groups');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('companies');
        Schema::dropIfExists('shift_schedules');
    }
};
