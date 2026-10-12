<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Frontline worker designation (doctor, LHW, CHW, nurse, other). Additive
     * and nullable: only screener memberships and invitations carry a value.
     */
    public function up(): void
    {
        Schema::table('team_members', function (Blueprint $table) {
            $table->string('designation', 20)->nullable()->after('role');
        });

        Schema::table('team_invitations', function (Blueprint $table) {
            $table->string('designation', 20)->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('team_invitations', function (Blueprint $table) {
            $table->dropColumn('designation');
        });

        Schema::table('team_members', function (Blueprint $table) {
            $table->dropColumn('designation');
        });
    }
};
