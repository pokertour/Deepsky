<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Les abonnés inscrits avant l'introduction du double opt-in sont considérés confirmés.
     */
    public function up(): void
    {
        DB::table('newsletter_subscribers')
            ->whereNull('confirmed_at')
            ->update(['confirmed_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        // Impossible de distinguer les abonnés backfillés ; rien à annuler.
    }
};
