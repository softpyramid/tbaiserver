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
        Schema::table('population_denominators', function (Blueprint $table) {
            $table->string('status', 16)->default('confirmed')->after('population');
            $table->string('reference_url')->nullable()->after('source_year');
        });

        // A population figure is required to confirm a denominator; rows
        // without one are placeholders waiting for a PBS figure.
        Schema::table('population_denominators', function (Blueprint $table) {
            $table->unsignedBigInteger('population')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('population_denominators', function (Blueprint $table) {
            $table->unsignedBigInteger('population')->nullable(false)->change();
            $table->dropColumn(['status', 'reference_url']);
        });
    }
};
