<?php

namespace App\Models;

use App\Enums\LoginEventType;
use Database\Factories\LoginEventFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginEvent extends Model
{
    /** @use HasFactory<LoginEventFactory> */
    use HasFactory, MassPrunable;

    public const int RETENTION_DAYS = 90;

    protected $fillable = [
        'user_id',
        'email',
        'type',
        'ip_address',
        'user_agent',
        'device_hash',
    ];

    protected function casts(): array
    {
        return [
            'type' => LoginEventType::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays(self::RETENTION_DAYS));
    }

    /**
     * Navigateur et système lisibles, tirés du user agent.
     */
    public function deviceLabel(): string
    {
        $agent = (string) $this->user_agent;

        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Safari/') => 'Safari',
            default => __('Navigateur inconnu'),
        };
        $system = match (true) {
            str_contains($agent, 'iPhone') || str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'Mac OS X') => 'macOS',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Linux') => 'Linux',
            default => null,
        };

        return $system ? "{$browser} · {$system}" : $browser;
    }
}
