<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Enums\LoginEventType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LoginRequest;
use App\Models\User;
use App\Services\Security\LoginActivityRecorder;
use App\Services\Security\TwoFactorAuthentication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TwoFactorChallengeController extends Controller
{
    private const int MAX_ATTEMPTS = 5;

    public function create(Request $request): View|RedirectResponse
    {
        if ($this->pendingUser($request) === null) {
            return redirect()->route('admin.login');
        }

        return view('admin.auth.two-factor-challenge');
    }

    public function store(Request $request, TwoFactorAuthentication $twoFactor, LoginActivityRecorder $activity): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if ($user === null) {
            return redirect()->route('admin.login')
                ->withErrors(['email' => __('La vérification a expiré. Reconnecte-toi.')]);
        }

        $data = $request->validate([
            'code' => ['nullable', 'required_without:recovery_code', 'string', 'max:16'],
            'recovery_code' => ['nullable', 'string', 'max:32'],
        ], ['code.required_without' => __('Saisis le code affiché par ton application.')]);

        $throttleKey = "admin-two-factor:{$user->id}";

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $request->session()->forget(LoginRequest::PENDING_TWO_FACTOR_SESSION_KEY);
            $activity->record(LoginEventType::LockedOut, $request, $user);

            return redirect()->route('admin.login')
                ->withErrors(['email' => __('Trop de codes invalides. Reconnecte-toi dans quelques minutes.')]);
        }

        $isValid = filled($data['recovery_code'] ?? null)
            ? $twoFactor->useRecoveryCode($user, $data['recovery_code'])
            : $twoFactor->verifyTotp($user, (string) $data['code']);

        if (! $isValid) {
            RateLimiter::hit($throttleKey, LoginRequest::LOCKOUT_SECONDS);
            $activity->record(LoginEventType::TwoFactorFailed, $request, $user);

            throw ValidationException::withMessages([
                filled($data['recovery_code'] ?? null) ? 'recovery_code' : 'code' => __('Ce code n’est pas valide.'),
            ]);
        }

        RateLimiter::clear($throttleKey);
        $remember = (bool) $request->session()->pull(LoginRequest::PENDING_TWO_FACTOR_SESSION_KEY)['remember'];

        Auth::login($user, $remember);
        $request->session()->regenerate();
        $activity->record(LoginEventType::Succeeded, $request, $user);

        if (filled($data['recovery_code'] ?? null)) {
            $request->session()->flash('warning', __('Code de secours utilisé. Il en reste :count.', ['count' => $twoFactor->remainingRecoveryCodes($user)]));
        }

        return redirect()->intended(route('admin.dashboard'));
    }

    private function pendingUser(Request $request): ?User
    {
        $pending = $request->session()->get(LoginRequest::PENDING_TWO_FACTOR_SESSION_KEY);

        if (! is_array($pending) || ($pending['expires_at'] ?? 0) < now()->getTimestamp()) {
            $request->session()->forget(LoginRequest::PENDING_TWO_FACTOR_SESSION_KEY);

            return null;
        }

        $user = User::find($pending['user_id'] ?? null);

        return $user?->isAdmin() && $user->hasTwoFactorEnabled() ? $user : null;
    }
}
