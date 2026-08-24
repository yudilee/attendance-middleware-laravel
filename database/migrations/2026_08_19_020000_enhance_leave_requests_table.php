<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('leave_requests', 'category')) {
                $table->string('category', 20)->default('leave')->index();
            }
            if (!Schema::hasColumn('leave_requests', 'permit_type')) {
                $table->string('permit_type', 50)->nullable()->index();
            }
            if (!Schema::hasColumn('leave_requests', 'expected_time')) {
                $table->string('expected_time', 20)->nullable();
            }
            if (!Schema::hasColumn('leave_requests', 'attachment_path')) {
                $table->string('attachment_path', 500)->nullable();
            }
            if (!Schema::hasColumn('leave_requests', 'admin_notes')) {
                $table->text('admin_notes')->nullable();
            }
            if (!Schema::hasColumn('leave_requests', 'processed_at')) {
                $table->timestamp('processed_at')->nullable();
            }
            if (!Schema::hasColumn('leave_requests', 'processed_by')) {
                $table->string('processed_by', 50)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropColumn([
                'category',
                'permit_type',
                'expected_time',
                'attachment_path',
                'admin_notes',
                'processed_at',
                'processed_by',
            ]);
        });
    }
};
