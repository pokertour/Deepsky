<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('notifications.alert_security_events', true);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('notifications.alert_security_events');
    }
};
