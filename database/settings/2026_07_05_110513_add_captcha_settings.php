<?php

use App\Enums\CaptchaProvider;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('integrations.captcha_provider', CaptchaProvider::None->value);
        $this->migrator->add('integrations.captcha_site_key', null);
        $this->migrator->addEncrypted('integrations.captcha_secret_key', null);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('integrations.captcha_provider');
        $this->migrator->deleteIfExists('integrations.captcha_site_key');
        $this->migrator->deleteIfExists('integrations.captcha_secret_key');
    }
};
