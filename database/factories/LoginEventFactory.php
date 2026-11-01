<?php

namespace Database\Factories;

use App\Enums\LoginEventType;
use App\Models\LoginEvent;
use App\Models\User;
use App\Services\Security\LoginActivityRecorder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoginEvent>
 */
class LoginEventFactory extends Factory
{
    public function definition(): array
    {
        $userAgent = fake()->userAgent();

        return [
            'user_id' => User::factory(),
            'email' => fake()->safeEmail(),
            'type' => LoginEventType::Succeeded,
            'ip_address' => fake()->ipv4(),
            'user_agent' => $userAgent,
            'device_hash' => LoginActivityRecorder::deviceHash($userAgent),
        ];
    }
}
