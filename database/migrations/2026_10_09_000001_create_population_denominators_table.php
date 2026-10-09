<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The population each area's notification rate is measured against.
     * One row per district or province per year, entered by a TB-AI admin.
     * Production starts empty, so the notification card answers "Population
     * not set" until someone records a figure.
     */
    public function up(): void
    {
        Schema::create('population_denominators', function (Blueprint $table) {
            $table->id();
            $table->string('area_type', 16);
            $table->unsignedBigInteger('area_id');
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('population');
            $table->string('source');
            $table->unsignedSmallInteger('source_year')->nullable();
            $table->text('method_note')->nullable();
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['area_type', 'area_id', 'year']);
            $table->index(['area_type', 'area_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('population_denominators');
    }
};
