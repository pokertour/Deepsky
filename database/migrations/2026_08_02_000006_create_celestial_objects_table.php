<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('celestial_objects', function (Blueprint $table) {
            $table->id();
            $table->string('catalog', 20);
            $table->string('catalog_number', 50);
            $table->string('name')->nullable();
            $table->json('alternative_names')->nullable();
            $table->string('type', 80)->nullable()->index();
            $table->string('constellation', 80)->nullable()->index();
            $table->decimal('ra_deg', 10, 7);
            $table->decimal('dec_deg', 10, 7);
            $table->decimal('magnitude', 7, 2)->nullable();
            $table->decimal('surface_brightness', 7, 2)->nullable();
            $table->decimal('angular_width_arcmin', 10, 3)->nullable();
            $table->decimal('angular_height_arcmin', 10, 3)->nullable();
            $table->decimal('distance_ly', 18, 3)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['catalog', 'catalog_number']);
            $table->index(['dec_deg', 'ra_deg']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('celestial_objects');
    }
};
