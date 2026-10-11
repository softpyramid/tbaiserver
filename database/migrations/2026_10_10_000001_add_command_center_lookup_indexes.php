<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_returns', function (Blueprint $table) {
            $table->index(['reported_on', 'facility_id'], 'daily_returns_reported_on_facility_id_index');
        });

        Schema::table('facilities', function (Blueprint $table) {
            $table->index(['city_id', 'sector', 'status'], 'facilities_city_sector_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('daily_returns', function (Blueprint $table) {
            $table->dropIndex('daily_returns_reported_on_facility_id_index');
        });

        Schema::table('facilities', function (Blueprint $table) {
            $table->dropIndex('facilities_city_sector_status_index');
        });
    }
};
