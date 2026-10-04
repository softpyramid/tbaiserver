<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A staging overlay for the Public, Private and PPM tables of the command
     * center: one row of published district figures per district and sector.
     * The sector API reads a row when it exists and computes the figures from
     * daily returns when it does not. Production starts with this table empty.
     * Aggregate figures only; no patient identifier is stored.
     */
    public function up(): void
    {
        Schema::create('sector_indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('city_id')->constrained()->cascadeOnDelete();
            $table->string('sector', 10);
            $table->unsignedInteger('suspects')->default(0);
            $table->unsignedInteger('tested')->default(0);
            $table->unsignedInteger('positive')->default(0);
            $table->unsignedInteger('started_treatment')->default(0);
            $table->double('positivity');
            $table->double('success_pct');
            $table->double('ltfu_pct');
            $table->double('tat_days');
            $table->unsignedInteger('mdr_rr')->default(0);
            $table->unsignedInteger('ai_tests')->default(0);
            $table->unsignedInteger('bmus')->default(0);
            $table->timestamps();

            $table->unique(['city_id', 'sector']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sector_indicators');
    }
};
