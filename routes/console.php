<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

Artisan::command('mahj:admin {email} {--name=Mahj Admin}', function (): int {
    $email = Str::lower(trim((string) $this->argument('email')));
    $password = $this->secret('Admin password');

    if ($password === null || strlen($password) < 8) {
        $this->error('Password must contain at least 8 characters.');

        return self::FAILURE;
    }

    User::query()->updateOrCreate(
        ['email' => $email],
        [
            'name' => (string) $this->option('name'),
            'password' => Hash::make($password),
            'email_verified_at' => now(),
            'is_admin' => true,
            'is_suspended' => false,
        ],
    );

    $this->info('Mahj admin account is ready.');

    return self::SUCCESS;
})->purpose('Create or update a Mahj admin account');
