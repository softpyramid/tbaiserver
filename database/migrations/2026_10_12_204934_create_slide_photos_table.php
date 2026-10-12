<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Slide photos attached to a BMU daily return. Files live on a private disk;
     * the original file name is never stored because it may carry patient details.
     */
    public function up(): void
    {
        Schema::create('slide_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('label', 60)->nullable();
            $table->string('disk', 40);
            $table->string('path');
            $table->string('thumbnail_path')->nullable();
            $table->string('mime_type', 60);
            $table->unsignedInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->timestamps();

            $table->index(['daily_return_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slide_photos');
    }
};
