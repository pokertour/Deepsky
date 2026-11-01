<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class SetUserPassword extends Command
{
    protected $signature = 'user:set-password {email? : The email of the user} {--password= : The new password (will prompt securely if omitted)}';

    protected $description = 'Set a user password safely through the model (handles hashing).';

    public function handle(): int
    {
        $email = $this->argument('email') ?? text(
            label: 'User email',
            required: true,
        );

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->components->error("No user found with email [{$email}].");

            return self::FAILURE;
        }

        $newPassword = $this->option('password') ?? password(
            label: 'New password',
            required: true,
        );

        $user->password = $newPassword;
        $user->save();

        $this->components->info("Password updated for [{$user->email}].");

        return self::SUCCESS;
    }
}
