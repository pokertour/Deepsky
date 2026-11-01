<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $password = Str::password(20);
        // Premier administrateur déclaré dans ADMIN_EMAILS, sinon une adresse d'exemple.
        $email = config('admin.emails')[0] ?? 'admin@example.com';

        User::factory()->create([
            'name' => 'Admin',
            'email' => $email,
            'password' => $password,
        ]);

        $this->command?->warn("Mot de passe généré pour {$email} : {$password}");
    }
}
