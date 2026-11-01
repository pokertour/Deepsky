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
        Schema::table('astrophotos', function (Blueprint $table) {
            $table->boolean('is_published')->default(true)->index();
            $table->timestamp('newsletter_notified_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('astrophotos', function (Blueprint $table) {
            $table->dropColumn(['is_published', 'newsletter_notified_at']);
        });
    }
};
