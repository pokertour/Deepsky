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
        Schema::create('celestial_wishlist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('celestial_object_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('priority')->default(2);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'celestial_object_id']);
            $table->index(['user_id', 'priority']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('celestial_wishlist_items');
    }
};
