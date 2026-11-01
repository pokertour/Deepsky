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
        Schema::create('imaging_plans', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->foreignId('observing_location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('equipment_setup_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 30)->default('planned');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['date', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('imaging_plans');
    }
};
