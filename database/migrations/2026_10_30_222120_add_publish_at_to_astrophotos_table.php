<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('astrophotos', function (Blueprint $table) {
            $table->timestamp('publish_at')->nullable()->index()->after('is_published');
        });
    }

    public function down(): void
    {
        Schema::table('astrophotos', function (Blueprint $table) {
            $table->dropColumn('publish_at');
        });
    }
};
