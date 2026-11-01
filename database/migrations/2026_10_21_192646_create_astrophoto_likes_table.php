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
        Schema::create('astrophoto_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('astrophoto_id')->constrained()->cascadeOnDelete();
            $table->char('visitor_hash', 64);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['astrophoto_id', 'visitor_hash']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('astrophoto_likes');
    }
};
