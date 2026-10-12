<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Named screenings: the patient's name and TB register number, recorded
     * at the start. Nullable, because screenings recorded before this change
     * carry neither. Shown only to the facility's staff and TB-AI admins.
     */
    public function up(): void
    {
        Schema::table('suspect_assessments', function (Blueprint $table) {
            $table->string('patient_name', 120)->nullable()->after('user_id');
            $table->string('register_number', 50)->nullable()->after('patient_name');
        });
    }

    public function down(): void
    {
        Schema::table('suspect_assessments', function (Blueprint $table) {
            $table->dropColumn(['patient_name', 'register_number']);
        });
    }
};
