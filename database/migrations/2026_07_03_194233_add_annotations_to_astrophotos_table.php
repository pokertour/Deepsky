<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('astrophotos', function (Blueprint $table) {
            $table->json('annotations')->nullable()->after('objects_in_field');
        });
    }

    public function down(): void
    {
        Schema::table('astrophotos', function (Blueprint $table) {
            $table->dropColumn('annotations');
        });
    }
};
