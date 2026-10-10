<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Estimated TB incidence per 100,000 population, entered by a TB-AI admin.
     * A country row (area_id null) is the national reference the card falls
     * back to until a province or district row exists. Production starts with
     * the seeded Pakistan 2026 figure; the seeder never updates an existing
     * row, so admin edits survive a reseed.
     */
    public function up(): void
    {
        Schema::create('incidence_estimates', function (Blueprint $table) {
            $table->id();
            $table->string('area_type', 16);
            $table->unsignedBigInteger('area_id')->nullable();
            $table->unsignedSmallInteger('year');
            $table->decimal('rate_per_100k', 8, 2);
            $table->decimal('lower_bound', 8, 2)->nullable();
            $table->decimal('upper_bound', 8, 2)->nullable();
            $table->unsignedInteger('estimated_cases')->nullable();
            $table->string('source');
            $table->string('method');
            $table->string('reference_url')->nullable();
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['area_type', 'area_id', 'year']);
            $table->index(['area_type', 'area_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidence_estimates');
    }
};
