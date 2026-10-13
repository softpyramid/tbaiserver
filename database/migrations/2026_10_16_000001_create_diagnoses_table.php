<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Simulated AI diagnosis demo records on a patient. The slide image lives
     * on the private slides disk; symptoms and notes are encrypted at rest.
     */
    public function up(): void
    {
        Schema::create('diagnoses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->string('test_type', 40);
            $table->string('image_source', 20);
            $table->string('image_disk', 40);
            $table->string('image_path');
            $table->string('thumbnail_path')->nullable();
            $table->string('mime_type', 60);
            $table->text('symptoms')->nullable();
            $table->text('notes')->nullable();
            $table->string('result', 20);
            $table->string('grade', 40)->nullable();
            $table->json('findings');
            $table->string('engine', 40);
            $table->string('engine_version', 40);
            $table->boolean('simulated')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('analysed_at');
            $table->timestamps();

            $table->index(['patient_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagnoses');
    }
};
