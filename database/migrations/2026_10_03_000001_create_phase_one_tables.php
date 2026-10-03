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
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('provinces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('code')->unique();
            $table->decimal('latitude', 8, 5);
            $table->decimal('longitude', 8, 5);
            $table->timestamps();
        });

        Schema::create('talukas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('city_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->timestamps();

            $table->unique(['city_id', 'slug']);
        });

        Schema::create('facilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('city_id')->constrained()->restrictOnDelete();
            $table->foreignId('taluka_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sector');
            $table->string('kind');
            $table->string('code')->nullable()->unique();
            $table->string('status')->default('active');
            $table->decimal('latitude', 8, 5)->nullable();
            $table->decimal('longitude', 8, 5)->nullable();
            $table->timestamps();
        });

        Schema::create('programme_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->string('scope_type')->nullable();
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->timestamps();

            $table->unique(
                ['user_id', 'role', 'scope_type', 'scope_id'],
                'programme_assignments_scope_unique',
            );
        });

        Schema::create('daily_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->date('reported_on');
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('suspects');
            $table->unsignedInteger('tested');
            $table->unsignedInteger('positive');
            $table->unsignedInteger('negative');
            $table->unsignedInteger('started_treatment');
            $table->unsignedInteger('follow_up');
            $table->decimal('median_tat_days', 5, 2)->nullable();
            $table->timestamps();

            $table->unique(['facility_id', 'reported_on']);
        });

        Schema::create('daily_return_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('changed_at');
            $table->json('old_values');
            $table->json('new_values');
        });

        Schema::create('screening_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('occurred_on');
            $table->json('answers');
            $table->unsignedInteger('score');
            $table->boolean('is_suspect');
            $table->timestamps();
        });

        Schema::create('incidence_targets', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month')->nullable();
            $table->decimal('value', 8, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incidence_targets');
        Schema::dropIfExists('screening_sessions');
        Schema::dropIfExists('daily_return_revisions');
        Schema::dropIfExists('daily_returns');
        Schema::dropIfExists('programme_assignments');
        Schema::dropIfExists('facilities');
        Schema::dropIfExists('talukas');
        Schema::dropIfExists('cities');
        Schema::dropIfExists('provinces');
        Schema::dropIfExists('countries');
    }
};
