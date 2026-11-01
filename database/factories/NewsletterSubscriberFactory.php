<?php

namespace Database\Factories;

use App\Models\NewsletterSubscriber;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @extends Factory<NewsletterSubscriber>
 */
class NewsletterSubscriberFactory extends Factory
{
    /**
     * @return array{email: string, unsubscribe_token: string, confirmed_at: Carbon}
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'unsubscribe_token' => Str::random(40),
            'confirmed_at' => now(),
        ];
    }

    public function unconfirmed(): static
    {
        return $this->state(fn (): array => [
            'confirmed_at' => null,
            'confirmation_token' => Str::random(40),
        ]);
    }
}
