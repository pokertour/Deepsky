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
        Schema::create('imaging_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('imaging_plan_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('observing_location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('equipment_setup_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->json('weather_data')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['started_at', 'ended_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('imaging_sessions');
    }
};
