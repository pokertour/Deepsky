<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('mail.host', env('MAIL_HOST', '127.0.0.1'));
        $this->migrator->add('mail.port', (int) env('MAIL_PORT', 2525));
        $this->migrator->add('mail.username', env('MAIL_USERNAME'));
        $this->migrator->addEncrypted('mail.password', env('MAIL_PASSWORD'));
        $this->migrator->add('mail.scheme', env('MAIL_SCHEME'));
        $this->migrator->add('mail.from_address', env('MAIL_FROM_ADDRESS', 'hello@example.com'));
        $this->migrator->add('mail.from_name', env('MAIL_FROM_NAME', config('app.name')));
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('mail.host');
        $this->migrator->deleteIfExists('mail.port');
        $this->migrator->deleteIfExists('mail.username');
        $this->migrator->deleteIfExists('mail.password');
        $this->migrator->deleteIfExists('mail.scheme');
        $this->migrator->deleteIfExists('mail.from_address');
        $this->migrator->deleteIfExists('mail.from_name');
    }
};
