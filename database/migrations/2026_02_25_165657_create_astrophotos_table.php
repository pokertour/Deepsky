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
        Schema::create('astrophotos', function (Blueprint $table) {
            $table->id();
            $table->string('title'); // ex: Bode's Galaxy
            $table->string('target')->nullable(); // ex: Messier 81 - Ursa Major
            $table->string('image_path');
            $table->date('captured_at'); // 05/08/2024

            // EXIF / COCKPIT DATA (Basé sur ton image)
            $table->string('exposure')->nullable(); // ex: 12.7h
            $table->integer('gain')->nullable(); // 100
            $table->integer('offset')->nullable(); // 50
            $table->string('sensor_temp')->nullable(); // -20.0°C
            $table->integer('focal_length')->nullable(); // 580mm
            $table->string('filters')->nullable(); // L-RGB / SHO

            // EQUIPMENT SETUP
            $table->foreignId('camera_id')
                ->nullable()
                ->constrained('setup_items')
                ->nullOnDelete();

            $table->foreignId('mount_id')
                ->nullable()
                ->constrained('setup_items')
                ->nullOnDelete();

            $table->foreignId('optical_tube_id')
                ->nullable()
                ->constrained('setup_items')
                ->nullOnDelete();

            $table->text('description')->nullable(); // Ta description longue sous la photo
            $table->string('location_tag')->nullable(); // Le badge bleu (Provence, Alpes, Ardèche)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('astrophotos');
    }
};
