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
        Schema::create('imaging_plan_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('imaging_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('celestial_object_id')->constrained()->restrictOnDelete();
            $table->dateTime('planned_start_at');
            $table->dateTime('planned_end_at');
            $table->unsignedSmallInteger('position');
            $table->unsignedTinyInteger('score_snapshot')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['imaging_plan_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('imaging_plan_targets');
    }
};
