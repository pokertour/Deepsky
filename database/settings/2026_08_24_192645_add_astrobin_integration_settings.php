<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->addEncrypted('integrations.astrobin_api_key', null);
        $this->migrator->addEncrypted('integrations.astrobin_api_secret', null);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('integrations.astrobin_api_key');
        $this->migrator->deleteIfExists('integrations.astrobin_api_secret');
    }
};
