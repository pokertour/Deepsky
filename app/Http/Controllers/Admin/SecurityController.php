<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoginEvent;
use App\Services\Security\LoginActivityRecorder;
use App\Services\Security\TotpAuthenticator;
use App\Services\Security\TwoFactorAuthentication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SecurityController extends Controller
{
    private const string PENDING_SECRET_SESSION_KEY = 'admin.two_factor_pending_secret';

    public function __construct(private TwoFactorAuthentication $twoFactor) {}

    public function edit(Request $request, TotpAuthenticator $totp): View
    {
        $user = $request->user();
        $pendingSecret = null;

        if (! $user->hasTwoFactorEnabled()) {
            $pendingSecret = $request->session()->get(self::PENDING_SECRET_SESSION_KEY) ?? $totp->generateSecret();
            $request->session()->put(self::PENDING_SECRET_SESSION_KEY, $pendingSecret);
        }

        return view('admin.security.edit', [
            'user' => $user,
            'pendingSecret' => $pendingSecret,
            'provisioningUri' => $pendingSecret ? $totp->provisioningUri($pendingSecret, $user->email, 'Deepsky') : null,
            'remainingRecoveryCodes' => $this->twoFactor->remainingRecoveryCodes($user),
            'loginEvents' => LoginEvent::query()->latest()->limit(20)->get(),
            'currentDeviceHash' => LoginActivityRecorder::deviceHash($request->userAgent()),
        ]);
    }

    public function enableTwoFactor(Request $request, TotpAuthenticator $totp): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:16']], ['code.required' => __('Saisis le code affiché par ton application.')]);
        $secret = $request->session()->get(self::PENDING_SECRET_SESSION_KEY);

        if ($secret === null || $totp->verify($secret, $data['code']) === null) {
            throw ValidationException::withMessages(['code' => __('Code invalide : vérifie l’heure de ton téléphone et réessaie.')]);
        }

        $codes = $this->twoFactor->enable($request->user(), $secret);
        $request->session()->forget(self::PENDING_SECRET_SESSION_KEY);

        return redirect()->route('admin.security.edit')
            ->with('success', __('Double authentification activée.'))
            ->with('recoveryCodes', $codes);
    }

    public function disableTwoFactor(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'string', 'current_password']], $this->passwordMessages());

        $this->twoFactor->disable($request->user());

        return redirect()->route('admin.security.edit')->with('warning', __('Double authentification désactivée.'));
    }

    public function logoutOtherSessions(Request $request): RedirectResponse
    {
        $request->validateWithBag('sessions', ['password' => ['required', 'string', 'current_password']], $this->passwordMessages());

        Auth::logoutOtherDevices($request->input('password'));

        return redirect()->route('admin.security.edit')->with('success', __('Toutes tes autres sessions ont été fermées.'));
    }

    public function regenerateRecoveryCodes(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'string', 'current_password']], $this->passwordMessages());

        abort_unless($request->user()->hasTwoFactorEnabled(), 404);

        return redirect()->route('admin.security.edit')
            ->with('success', __('Nouveaux codes de secours générés : les anciens ne fonctionnent plus.'))
            ->with('recoveryCodes', $this->twoFactor->regenerateRecoveryCodes($request->user()));
    }

    /** @return array<string, string> */
    private function passwordMessages(): array
    {
        return [
            'password.required' => __('Saisis ton mot de passe actuel.'),
            'password.current_password' => __('Mot de passe incorrect.'),
        ];
    }
}
