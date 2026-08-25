<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('punch_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('punch_logs', 'device_sn')) {
                $table->string('device_sn', 100)->nullable()->index()->after('device_uuid');
            }
            if (!Schema::hasColumn('punch_logs', 'device_name')) {
                $table->string('device_name', 150)->nullable()->after('device_sn');
            }
            if (!Schema::hasColumn('punch_logs', 'branch_id')) {
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete()->after('device_name');
            }
            if (!Schema::hasColumn('punch_logs', 'punch_source')) {
                $table->string('punch_source', 50)->default('mobile_app')->index()->after('adms_status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('punch_logs', function (Blueprint $table) {
            if (Schema::hasColumn('punch_logs', 'branch_id')) {
                $table->dropForeign(['branch_id']);
                $table->dropColumn('branch_id');
            }
            if (Schema::hasColumn('punch_logs', 'device_sn')) {
                $table->dropColumn('device_sn');
            }
            if (Schema::hasColumn('punch_logs', 'device_name')) {
                $table->dropColumn('device_name');
            }
            if (Schema::hasColumn('punch_logs', 'punch_source')) {
                $table->dropColumn('punch_source');
            }
        });
    }
};
