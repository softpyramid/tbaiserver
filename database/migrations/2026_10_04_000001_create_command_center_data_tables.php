<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aggregate programme data the command center shows but daily returns do
     * not carry. Every table is counts per facility and month (or per
     * district and year). No patient identifier is stored anywhere here.
     * Production starts with all of these empty, so the API answers null.
     */
    public function up(): void
    {
        Schema::create('treatment_outcomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->string('month', 7);
            $table->unsignedInteger('cohort_size')->default(0);
            $table->unsignedInteger('successful')->default(0);
            $table->unsignedInteger('lost_to_follow_up')->default(0);
            $table->unsignedInteger('died')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->timestamps();

            $table->unique(['facility_id', 'month']);
            $table->index('month');
        });

        Schema::create('mdr_rr_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->string('month', 7);
            $table->unsignedInteger('cases')->default(0);
            $table->timestamps();

            $table->unique(['facility_id', 'month']);
            $table->index('month');
        });

        Schema::create('ai_test_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->string('month', 7);
            $table->unsignedInteger('tests')->default(0);
            $table->timestamps();

            $table->unique(['facility_id', 'month']);
            $table->index('month');
        });

        Schema::create('contact_tracing', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->string('month', 7);
            $table->unsignedInteger('contacts_listed')->default(0);
            $table->unsignedInteger('contacts_evaluated')->default(0);
            $table->timestamps();

            $table->unique(['facility_id', 'month']);
            $table->index('month');
        });

        Schema::create('hiv_testing', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->string('month', 7);
            $table->unsignedInteger('tb_patients')->default(0);
            $table->unsignedInteger('hiv_status_recorded')->default(0);
            $table->timestamps();

            $table->unique(['facility_id', 'month']);
            $table->index('month');
        });

        Schema::create('district_indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('city_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('population')->nullable();
            $table->decimal('density_per_km2', 9, 2)->nullable();
            $table->decimal('malnutrition_pct', 5, 2)->nullable();
            $table->decimal('socioeconomic_index', 5, 2)->nullable();
            $table->decimal('avg_distance_km', 6, 2)->nullable();
            $table->timestamps();

            $table->unique(['city_id', 'year']);
        });

        Schema::create('incidence_monthly', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')->constrained()->cascadeOnDelete();
            $table->string('month', 7);
            $table->decimal('value', 8, 2);
            $table->timestamps();

            $table->unique(['province_id', 'month']);
        });

        Schema::create('ai_test_fixtures', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('summary');
            $table->string('location');
            $table->string('symptoms');
            $table->unsignedTinyInteger('probability');
            $table->string('laboratory');
            $table->string('history');
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'ai_test_fixtures',
            'incidence_monthly',
            'district_indicators',
            'hiv_testing',
            'contact_tracing',
            'ai_test_counts',
            'mdr_rr_cases',
            'treatment_outcomes',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
