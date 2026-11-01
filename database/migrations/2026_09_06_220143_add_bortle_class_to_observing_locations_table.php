<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('observing_locations', function (Blueprint $table) {
            $table->unsignedTinyInteger('bortle_class')->nullable()->after('altitude');
        });
    }

    public function down(): void
    {
        Schema::table('observing_locations', function (Blueprint $table) {
            $table->dropColumn('bortle_class');
        });
    }
};
