<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->addEncrypted('integrations.astrometry_api_key', env('ASTROMETRY_API_KEY'));
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('integrations.astrometry_api_key');
    }
};
