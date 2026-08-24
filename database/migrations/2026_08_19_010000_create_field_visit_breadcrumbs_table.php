<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('field_visit_breadcrumbs')) {
            Schema::create('field_visit_breadcrumbs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('field_visit_id')->constrained('field_visits')->cascadeOnDelete();
                $table->double('latitude');
                $table->double('longitude');
                $table->double('speed')->nullable(); // km/h or m/s
                $table->double('accuracy')->nullable(); // meters
                $table->double('heading')->nullable(); // degrees 0-360
                $table->timestamp('recorded_at')->useCurrent();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['field_visit_id', 'recorded_at'], 'idx_breadcrumb_visit_time');
            });
        }

        // Add total_distance_km to field_visits if not present
        if (Schema::hasTable('field_visits') && !Schema::hasColumn('field_visits', 'total_distance_km')) {
            Schema::table('field_visits', function (Blueprint $table) {
                $table->double('total_distance_km')->nullable()->after('duration_minutes');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('field_visit_breadcrumbs');
        if (Schema::hasTable('field_visits') && Schema::hasColumn('field_visits', 'total_distance_km')) {
            Schema::table('field_visits', function (Blueprint $table) {
                $table->dropColumn('total_distance_km');
            });
        }
    }
};
