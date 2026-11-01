<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('astrophotos', function (Blueprint $table) {
            $table->string('title_en')->nullable()->after('title');
            $table->text('description_en')->nullable()->after('description');
            $table->text('object_description_en')->nullable()->after('object_description');
        });
    }

    public function down(): void
    {
        Schema::table('astrophotos', function (Blueprint $table) {
            $table->dropColumn(['title_en', 'description_en', 'object_description_en']);
        });
    }
};
