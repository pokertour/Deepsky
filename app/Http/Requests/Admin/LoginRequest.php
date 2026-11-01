<?php

namespace App\Http\Requests\Admin;

use App\Enums\LoginEventType;
use App\Models\User;
use App\Services\Security\LoginActivityRecorder;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Échecs tolérés par compte (toutes IP confondues) avant verrouillage temporaire :
     * complète le throttle par IP de la route contre le brute-force distribué.
     */
    public const int MAX_ATTEMPTS_PER_ACCOUNT = 10;

    public const int LOCKOUT_SECONDS = 900;

    public const string PENDING_TWO_FACTOR_SESSION_KEY = 'admin.two_factor_login';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:1024'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Vérifie les identifiants. Si le compte a la double authentification, la session
     * n'est pas encore ouverte : l'utilisateur est mis en attente du code TOTP.
     *
     * @return bool true si une vérification à deux facteurs est requise
     */
    public function authenticate(): bool
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::validate($this->safe()->only(['email', 'password']))) {
            RateLimiter::hit($this->throttleKey(), self::LOCKOUT_SECONDS);
            $this->recordActivity(LoginEventType::Failed, Auth::getLastAttempted());

            throw ValidationException::withMessages(['email' => __('Ces identifiants ne correspondent pas à nos enregistrements.')]);
        }

        /** @var User $user */
        $user = Auth::getLastAttempted();

        if (! $user->isAdmin()) {
            $this->recordActivity(LoginEventType::Failed, $user);

            throw ValidationException::withMessages(['email' => __('Ce compte ne dispose pas des droits d’administration.')]);
        }

        RateLimiter::clear($this->throttleKey());
        $this->session()->regenerate();

        if ($user->hasTwoFactorEnabled()) {
            $this->session()->put(self::PENDING_TWO_FACTOR_SESSION_KEY, [
                'user_id' => $user->id,
                'remember' => $this->boolean('remember'),
                'expires_at' => now()->addMinutes(5)->getTimestamp(),
            ]);

            return true;
        }

        Auth::login($user, $this->boolean('remember'));
        $this->recordActivity(LoginEventType::Succeeded, $user);

        return false;
    }

    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS_PER_ACCOUNT)) {
            return;
        }

        event(new Lockout($this));
        $this->recordActivity(LoginEventType::LockedOut);

        $minutes = (int) ceil(RateLimiter::availableIn($this->throttleKey()) / 60);

        throw ValidationException::withMessages([
            'email' => "Trop de tentatives de connexion pour ce compte. Réessayez dans {$minutes} min.",
        ]);
    }

    private function recordActivity(LoginEventType $type, mixed $user = null): void
    {
        app(LoginActivityRecorder::class)->record($type, $this, $user instanceof User ? $user : null, (string) $this->input('email'));
    }

    private function throttleKey(): string
    {
        return 'admin-login:'.hash('sha256', mb_strtolower((string) $this->input('email')));
    }
}
