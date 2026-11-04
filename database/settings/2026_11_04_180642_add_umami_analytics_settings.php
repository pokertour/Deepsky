<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('integrations.umami_script_url', null);
        $this->migrator->add('integrations.umami_website_id', null);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('integrations.umami_script_url');
        $this->migrator->deleteIfExists('integrations.umami_website_id');
    }
};
