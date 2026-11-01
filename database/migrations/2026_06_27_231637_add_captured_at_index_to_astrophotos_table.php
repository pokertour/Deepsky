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
            // Accélère le tri/pagination de la page d'accueil (orderBy captured_at desc).
            $table->index('captured_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('astrophotos', function (Blueprint $table) {
            $table->dropIndex(['captured_at']);
        });
    }
};
