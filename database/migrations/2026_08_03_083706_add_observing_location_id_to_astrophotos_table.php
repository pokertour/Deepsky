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
            $table->foreignId('observing_location_id')
                ->nullable()
                ->after('imaging_session_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('astrophotos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('observing_location_id');
        });
    }
};
