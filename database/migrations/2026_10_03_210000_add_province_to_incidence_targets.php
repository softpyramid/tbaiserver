<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Provincial planning numbers. One yearly target per province.
     * Month stays empty. No patient identifier.
     */
    public function up(): void
    {
        Schema::table('incidence_targets', function (Blueprint $table) {
            $table->foreignId('province_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unique(['province_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::table('incidence_targets', function (Blueprint $table) {
            $table->dropUnique(['province_id', 'year']);
            $table->dropConstrainedForeignId('province_id');
        });
    }
};
