<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('astrophotos')->where('is_published', true)->whereNull('newsletter_notified_at')
            ->update(['newsletter_notified_at' => DB::raw('created_at')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Keep the notification history of existing publications.
    }
};
