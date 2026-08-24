<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Customers / Visitable Locations Master Table
        if (!Schema::hasTable('customers')) {
            Schema::create('customers', function (Blueprint $table) {
                $table->id();
                $table->string('name', 200);
                $table->text('address')->nullable();
                $table->string('city', 100)->nullable();
                $table->string('phone', 50)->nullable();
                $table->string('email', 150)->nullable();
                $table->double('latitude')->nullable();
                $table->double('longitude')->nullable();
                $table->string('customer_type', 30)->default('dealer'); // dealer, end_customer, warehouse, workshop, prospect, other
                $table->string('assigned_employee_id', 50)->nullable()->index();
                $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
                $table->boolean('is_active')->default(true);
                $table->text('notes')->nullable();
                $table->integer('odoo_partner_id')->nullable()->index();
                $table->timestamp('odoo_last_synced_at')->nullable();
                $table->timestamps();

                $table->foreign('assigned_employee_id')->references('employee_id')->on('employees')->nullOnDelete();
            });
        }

        // 2. Field Visits (Mechanic Storing & Sales Canvassing Visits)
        if (!Schema::hasTable('field_visits')) {
            Schema::create('field_visits', function (Blueprint $table) {
                $table->id();
                $table->string('employee_id', 50)->index();
                $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
                $table->string('visit_type', 30)->default('canvassing'); // storing, canvassing, delivery, service, survey, other
                $table->text('purpose')->nullable();
                $table->timestamp('check_in_at')->useCurrent();
                $table->double('check_in_lat')->nullable();
                $table->double('check_in_lng')->nullable();
                $table->timestamp('check_out_at')->nullable();
                $table->double('check_out_lat')->nullable();
                $table->double('check_out_lng')->nullable();
                $table->integer('duration_minutes')->nullable();
                $table->string('status', 20)->default('in_progress'); // in_progress, completed, cancelled
                $table->text('notes')->nullable();
                $table->text('result')->nullable();
                $table->string('device_uuid', 255)->nullable();
                $table->boolean('is_mock_location')->default(false);
                $table->integer('odoo_activity_id')->nullable()->index();
                $table->integer('odoo_lead_id')->nullable()->index();
                $table->timestamp('odoo_last_synced_at')->nullable();
                $table->timestamps();

                $table->foreign('employee_id')->references('employee_id')->on('employees')->cascadeOnDelete();
                $table->index(['employee_id', 'check_in_at'], 'idx_field_visit_emp_date');
            });
        }

        // 3. Field Visit Photos
        if (!Schema::hasTable('field_visit_photos')) {
            Schema::create('field_visit_photos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('field_visit_id')->constrained('field_visits')->cascadeOnDelete();
                $table->string('filename', 500);
                $table->string('caption', 200)->nullable();
                $table->string('photo_type', 20)->default('evidence'); // check_in, check_out, evidence, before, after
                $table->double('latitude')->nullable();
                $table->double('longitude')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        // 4. Field Tasks (Mechanic Dispatches & Sales Assignments)
        if (!Schema::hasTable('field_tasks')) {
            Schema::create('field_tasks', function (Blueprint $table) {
                $table->id();
                $table->string('employee_id', 50)->index();
                $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
                $table->string('title', 200);
                $table->text('description')->nullable();
                $table->string('task_type', 30)->default('canvass'); // storing, delivery, repair, inspection, canvass, follow_up
                $table->string('priority', 10)->default('medium'); // low, medium, high, urgent
                $table->string('status', 20)->default('pending'); // pending, in_progress, completed, cancelled
                $table->date('due_date')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->text('completed_notes')->nullable();
                $table->string('assigned_by', 100)->nullable();
                $table->foreignId('field_visit_id')->nullable()->constrained('field_visits')->nullOnDelete();
                $table->integer('odoo_activity_id')->nullable()->index();
                $table->timestamp('odoo_last_synced_at')->nullable();
                $table->timestamps();

                $table->foreign('employee_id')->references('employee_id')->on('employees')->cascadeOnDelete();
                $table->index(['employee_id', 'status'], 'idx_field_task_emp_status');
            });
        }

        // 5. Canvass Plans (Target visits & routes)
        if (!Schema::hasTable('canvass_plans')) {
            Schema::create('canvass_plans', function (Blueprint $table) {
                $table->id();
                $table->string('employee_id', 50)->index();
                $table->date('plan_date');
                $table->integer('target_visits')->default(5);
                $table->integer('actual_visits')->default(0);
                $table->json('customer_ids')->nullable();
                $table->json('route_order')->nullable();
                $table->text('notes')->nullable();
                $table->string('status', 20)->default('draft'); // draft, active, completed
                $table->string('created_by', 100)->nullable();
                $table->timestamps();

                $table->foreign('employee_id')->references('employee_id')->on('employees')->cascadeOnDelete();
                $table->unique(['employee_id', 'plan_date'], 'uq_canvass_plan_emp_date');
            });
        }

        // 6. Odoo Sync Logs
        if (!Schema::hasTable('odoo_sync_logs')) {
            Schema::create('odoo_sync_logs', function (Blueprint $table) {
                $table->id();
                $table->string('sync_type', 50); // customers_pull, customers_push, visits_push, tasks_pull, tasks_push, employees_pull, full_sync
                $table->string('direction', 10); // pull, push, both
                $table->integer('records_processed')->default(0);
                $table->integer('records_created')->default(0);
                $table->integer('records_updated')->default(0);
                $table->integer('records_failed')->default(0);
                $table->text('error_message')->nullable();
                $table->timestamp('started_at')->useCurrent();
                $table->timestamp('completed_at')->nullable();
                $table->string('status', 20)->default('running'); // running, completed, failed

                $table->index(['sync_type', 'started_at'], 'idx_odoo_sync_type_date');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('odoo_sync_logs');
        Schema::dropIfExists('canvass_plans');
        Schema::dropIfExists('field_tasks');
        Schema::dropIfExists('field_visit_photos');
        Schema::dropIfExists('field_visits');
        Schema::dropIfExists('customers');
    }
};
