<?php

namespace App\Models;

use Database\Factories\NewsletterSubscriberFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NewsletterSubscriber extends Model
{
    public const CONFIRMATION_VALIDITY_DAYS = 7;

    /** @use HasFactory<NewsletterSubscriberFactory> */
    use HasFactory;

    protected $fillable = [
        'email',
        'locale',
        'unsubscribe_token',
        'confirmation_token',
        'confirmed_at',
        'confirmation_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
            'confirmation_sent_at' => 'datetime',
        ];
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->whereNotNull('confirmed_at');
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null;
    }
}
