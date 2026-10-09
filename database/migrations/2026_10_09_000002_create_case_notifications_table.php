<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * TB case notifications per facility and month: the new and relapse cases
     * a facility notified. Aggregate counts only, no patient identifiers.
     * Production starts empty, so the notification card answers "No data".
     */
    public function up(): void
    {
        Schema::create('case_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->string('month', 7);
            $table->unsignedInteger('new_cases')->default(0);
            $table->unsignedInteger('relapse_cases')->default(0);
            $table->unsignedInteger('other_previously_treated')->default(0);
            $table->unsignedInteger('history_unknown')->default(0);
            $table->string('source');
            $table->timestamps();

            $table->unique(['facility_id', 'month']);
            $table->index('month');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_notifications');
    }
};
