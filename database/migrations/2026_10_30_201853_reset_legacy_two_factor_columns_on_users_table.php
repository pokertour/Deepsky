<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Les colonnes MFA proviennent de l'ancien panneau Filament, dans un format
     * incompatible avec la nouvelle double authentification : on repart de zéro,
     * la 2FA se réactive depuis Admin > Sécurité.
     */
    public function up(): void
    {
        DB::table('users')->update([
            'app_authentication_secret' => null,
            'app_authentication_recovery_codes' => null,
        ]);
    }

    public function down(): void
    {
        //
    }
};
