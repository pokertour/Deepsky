<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('equipment_setup_setup_item')) {
            return;
        }

        Schema::create('equipment_setup_setup_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_setup_id')->constrained()->cascadeOnDelete();
            $table->foreignId('setup_item_id')->constrained()->cascadeOnDelete();
            $table->string('role', 80)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('enabled')->default(true);
            $table->json('configuration')->nullable();
            $table->timestamps();
            $table->unique(['equipment_setup_id', 'setup_item_id']);
            $table->index(['equipment_setup_id', 'enabled', 'position'], 'equipment_setup_enabled_position_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_setup_setup_item');
    }
};
