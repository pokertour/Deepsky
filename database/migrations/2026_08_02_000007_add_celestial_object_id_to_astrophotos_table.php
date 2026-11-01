<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('astrophotos', function (Blueprint $table) {
            $table->foreignId('celestial_object_id')
                ->nullable()
                ->after('target')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('astrophotos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('celestial_object_id');
        });
    }
};
