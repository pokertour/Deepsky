<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('notifications.locale', 'fr');
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('notifications.locale');
    }
};
