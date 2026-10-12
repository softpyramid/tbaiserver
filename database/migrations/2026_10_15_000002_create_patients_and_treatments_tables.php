<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Patients per facility, their treatments (TB01 and TB03 fields) and
     * the monthly follow-up visits. Name, CNIC and phone are encrypted by the
     * model, so they are text columns; cnic_hash is an HMAC of the CNIC
     * digits for exact search. A suspect screening can point to a patient.
     */
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->text('name');
            $table->text('cnic')->nullable();
            $table->char('cnic_hash', 64)->nullable();
            $table->text('phone')->nullable();
            $table->string('gender', 16);
            $table->unsignedTinyInteger('age');
            $table->string('register_number', 50);
            $table->boolean('informed_of_data_use')->default(false);
            $table->timestamp('informed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['facility_id', 'register_number']);
            $table->index(['facility_id', 'cnic_hash']);
        });

        Schema::create('treatments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');
            $table->string('regimen_category', 32);
            $table->string('registration_type', 48);
            $table->string('site', 16);
            $table->string('tb03_number', 50)->nullable();
            $table->string('outcome', 32)->nullable();
            $table->date('outcome_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['patient_id', 'outcome']);
        });

        Schema::create('treatment_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('treatment_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('month_number');
            $table->date('scheduled_date');
            $table->date('attended_date')->nullable();
            $table->string('sputum_result', 16)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('missed_flagged_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['treatment_id', 'month_number']);
            $table->index('scheduled_date');
        });

        Schema::table('suspect_assessments', function (Blueprint $table) {
            $table->foreignId('patient_id')->nullable()->after('register_number')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('suspect_assessments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('patient_id');
        });

        Schema::dropIfExists('treatment_visits');
        Schema::dropIfExists('treatments');
        Schema::dropIfExists('patients');
    }
};
