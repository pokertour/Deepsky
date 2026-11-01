<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('newsletter_subscribers', function (Blueprint $table) {
            $table->string('confirmation_token', 40)->nullable()->after('unsubscribe_token');
            $table->timestamp('confirmed_at')->nullable()->after('confirmation_token');
        });
    }

    public function down(): void
    {
        Schema::table('newsletter_subscribers', function (Blueprint $table) {
            $table->dropColumn(['confirmation_token', 'confirmed_at']);
        });
    }
};
