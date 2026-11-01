<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->addEncrypted('integrations.gemini_api_key', null);
        $this->migrator->add('integrations.gemini_model', 'gemini-2.5-flash-lite');
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('integrations.gemini_api_key');
        $this->migrator->deleteIfExists('integrations.gemini_model');
    }
};
