<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where a target figure came from. Optional; the table still works without
     * a source, but showing one on the page no longer needs a comment.
     */
    public function up(): void
    {
        Schema::table('incidence_targets', function (Blueprint $table) {
            $table->string('source')->nullable()->after('value');
        });
    }

    public function down(): void
    {
        Schema::table('incidence_targets', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
