<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Score at or above which a screening is low priority; below it the band
     * is "none" (very low). Default 0, so every existing set (draft.1
     * included) bands exactly as before.
     */
    public function up(): void
    {
        Schema::table('suspect_rule_sets', function (Blueprint $table) {
            $table->unsignedSmallInteger('low_threshold')->default(0)->after('medium_threshold');
        });
    }

    public function down(): void
    {
        Schema::table('suspect_rule_sets', function (Blueprint $table) {
            $table->dropColumn('low_threshold');
        });
    }
};
