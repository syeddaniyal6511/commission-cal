<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->string('contract_no', 50)->unique();
            $table->decimal('annual_usage', 12, 2);
            $table->decimal('contract_value', 12, 2);
            $table->unsignedSmallInteger('contract_length');
            $table->decimal('risk_score', 5, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
