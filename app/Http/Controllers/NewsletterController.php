<?php

namespace App\Http\Controllers;

use App\Models\NewsletterSubscriber;
use App\Services\CaptchaService;
use App\Services\NewsletterConfirmationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class NewsletterController extends Controller
{
    private const string CONFIRMATION_SENT_MESSAGE = 'Un email de confirmation vient de vous être envoyé. Cliquez sur le lien qu’il contient pour valider votre inscription.';

    public function subscribe(Request $request, CaptchaService $captchaService, NewsletterConfirmationService $confirmation): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        if ($request->filled('website')) {
            return back()->with('newsletter_status', __(self::CONFIRMATION_SENT_MESSAGE));
        }

        $captchaField = $captchaService->provider()->responseFieldName();

        if ($captchaField !== null && ! $captchaService->verify($request->input($captchaField), $request->ip(), 'newsletter')) {
            return back()
                ->withInput($request->except('cf-turnstile-response', 'h-captcha-response'))
                ->withErrors(['email' => __('La vérification anti-robot a échoué. Veuillez réessayer.')]);
        }

        $subscriber = NewsletterSubscriber::firstOrCreate(
            ['email' => strtolower($validated['email'])],
            [
                'unsubscribe_token' => Str::random(40),
                'confirmation_token' => Str::random(40),
            ],
        );
        // Les emails partent dans la langue du site au moment de l'inscription.
        $subscriber->update(['locale' => app()->getLocale()]);

        if ($subscriber->isConfirmed()) {
            return back()->with('newsletter_status', __('Inscription confirmée. Vous serez averti à chaque nouvelle image.'));
        }

        if (! $confirmation->send($subscriber)) {
            return back()->with('newsletter_status', __('Un email de confirmation a déjà été envoyé récemment. Vérifiez votre boîte de réception et vos spams. Vous pourrez en demander un nouveau après cinq minutes.'));
        }

        return back()->with('newsletter_status', __(self::CONFIRMATION_SENT_MESSAGE));
    }

    public function confirm(string $token): View
    {
        $subscriber = NewsletterSubscriber::where('confirmation_token', $token)->first();

        if ($subscriber === null || ($subscriber->confirmation_sent_at ?? $subscriber->created_at)->lte(now()->subDays(NewsletterSubscriber::CONFIRMATION_VALIDITY_DAYS))) {
            return view('newsletter.status', [
                'success' => false,
                'message' => __('Ce lien de confirmation n\'est plus valide.'),
            ]);
        }

        $subscriber->update([
            'confirmed_at' => now(),
            'confirmation_token' => null,
        ]);

        return view('newsletter.status', [
            'success' => true,
            'message' => __('Inscription confirmée. Vous serez averti à chaque nouvelle image.'),
        ]);
    }

    /**
     * Les scanners de liens des messageries ouvrent les URL des emails : un simple GET
     * ne doit donc jamais désinscrire, il affiche une confirmation qui envoie un POST.
     */
    public function confirmUnsubscribe(string $token): View
    {
        if (! NewsletterSubscriber::where('unsubscribe_token', $token)->exists()) {
            return view('newsletter.status', [
                'success' => true,
                'message' => __('Vous êtes désinscrit de la newsletter.'),
            ]);
        }

        return view('newsletter.status', [
            'success' => true,
            'message' => __('Confirmez-vous ne plus recevoir d\'email à chaque nouvelle photo ?'),
            'unsubscribeToken' => $token,
        ]);
    }

    public function unsubscribe(string $token): View
    {
        NewsletterSubscriber::where('unsubscribe_token', $token)->delete();

        return view('newsletter.status', [
            'success' => true,
            'message' => __('Vous êtes désinscrit de la newsletter.'),
        ]);
    }
}
