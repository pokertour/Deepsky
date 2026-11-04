<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CaptchaProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TestNotificationRequest;
use App\Http\Requests\Admin\TestSmtpRequest;
use App\Http\Requests\Admin\UpdateNotificationSettingsRequest;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Services\CaptchaService;
use App\Services\Notifications\NightScopeNotificationDispatcher;
use App\Settings\IntegrationSettings;
use App\Settings\MailSettings;
use App\Settings\NotificationSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Throwable;

class SettingsController extends Controller
{
    public function edit(
        IntegrationSettings $integrations,
        MailSettings $mail,
        NotificationSettings $notifications,
    ): View {
        return view('admin.settings.edit', compact('integrations', 'mail', 'notifications'));
    }

    public function update(UpdateSettingsRequest $request, IntegrationSettings $integrations, MailSettings $mail): RedirectResponse
    {
        $data = $request->validated();
        $integrations->captcha_provider = CaptchaProvider::from($data['captcha_provider']);
        $integrations->captcha_site_key = $data['captcha_site_key'] ?: null;
        $integrations->umami_script_url = ($data['umami_script_url'] ?? null) ?: null;
        $integrations->umami_website_id = ($data['umami_website_id'] ?? null) ?: null;

        if (filled($data['astrometry_api_key'])) {
            $integrations->astrometry_api_key = $data['astrometry_api_key'];
        }
        if (filled($data['gemini_api_key'] ?? null)) {
            $integrations->gemini_api_key = $data['gemini_api_key'];
        }
        if (isset($data['gemini_model'])) {
            $integrations->gemini_model = $data['gemini_model'];
        }
        if (filled($data['captcha_secret_key'])) {
            $integrations->captcha_secret_key = $data['captcha_secret_key'];
        }
        $integrations->save();

        foreach (['host', 'port', 'username', 'scheme', 'from_address', 'from_name'] as $field) {
            $mail->{$field} = $data[$field] ?: null;
        }
        if (filled($data['password'])) {
            $mail->password = $data['password'];
        }
        $mail->save();

        return back()->with('success', __('Paramètres enregistrés.'));
    }

    public function updateNotifications(
        UpdateNotificationSettingsRequest $request,
        NotificationSettings $notifications,
    ): RedirectResponse {
        $data = $request->validated();

        $notifications->telegram_enabled = $data['telegram_enabled'];
        $notifications->telegram_chat_id = $data['telegram_chat_id'] ?: null;
        $notifications->gotify_enabled = $data['gotify_enabled'];
        $notifications->gotify_url = $data['gotify_url'] ?: null;
        $notifications->ntfy_enabled = $data['ntfy_enabled'];
        $notifications->ntfy_url = $data['ntfy_url'] ?: 'https://ntfy.sh';
        $notifications->ntfy_topic = $data['ntfy_topic'] ?: null;
        $notifications->mail_enabled = $data['mail_enabled'];
        $notifications->mail_recipient = $data['mail_recipient'] ?: null;
        $notifications->alert_daily_conditions = $data['alert_daily_conditions'];
        $notifications->alert_best_targets = $data['alert_best_targets'];
        $notifications->alert_weekly_report = $data['alert_weekly_report'];
        $notifications->alert_task_failures = $data['alert_task_failures'];
        $notifications->alert_security_events = $data['alert_security_events'];
        $notifications->alert_every_login = $data['alert_every_login'];
        $notifications->locale = $data['locale'] ?? $notifications->locale;
        $notifications->daily_min_score = (int) $data['daily_min_score'];
        $notifications->daily_time = $data['daily_time'];
        $notifications->weekly_time = $data['weekly_time'];

        if (filled($data['telegram_bot_token'])) {
            $notifications->telegram_bot_token = $data['telegram_bot_token'];
        }
        if (filled($data['gotify_token'])) {
            $notifications->gotify_token = $data['gotify_token'];
        }

        $notifications->save();

        return back()->with('success', __('Notifications NightScope enregistrées.'));
    }

    public function test(TestSmtpRequest $request, MailSettings $settings): RedirectResponse
    {
        config(['mail.mailers.admin_test' => $settings->toSmtpMailerConfig()]);
        app('mail.manager')->purge('admin_test');

        try {
            Mail::mailer('admin_test')->raw(__('Email de test envoyé depuis Deepsky.'), function ($message) use ($request, $settings): void {
                $message->to($request->validated('recipient'))->from($settings->from_address, $settings->from_name)->subject(__('Deepsky — test SMTP'));
            });
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', __('Connexion SMTP impossible. Vérifie les paramètres et réessaie.'));
        }

        return back()->with('success', __('Email de test envoyé.'));
    }

    public function testCaptcha(CaptchaService $captcha): RedirectResponse
    {
        $result = $captcha->testSecret();

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function testNotification(
        TestNotificationRequest $request,
        NightScopeNotificationDispatcher $dispatcher,
        NotificationSettings $notifications,
    ): RedirectResponse {
        if (! $notifications->hasReadyChannel()) {
            return back()->with('error', __('Active au moins un canal de notification et enregistre les paramètres.'));
        }

        try {
            $dispatcher->sendTest();
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', __('Impossible d’envoyer la notification de test.'));
        }

        return back()->with('success', __('Notification de test envoyée.'));
    }
}
