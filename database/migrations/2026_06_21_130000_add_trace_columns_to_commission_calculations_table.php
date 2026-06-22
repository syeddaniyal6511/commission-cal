<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commission_calculations', function (Blueprint $table) {
            $table->string('formula_version')->nullable()->after('formula_id');
            $table->json('steps_json')->nullable()->after('variables_json');
        });
    }

    public function down(): void
    {
        Schema::table('commission_calculations', function (Blueprint $table) {
            $table->dropColumn(['formula_version', 'steps_json']);
        });
    }
};
