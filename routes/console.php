<?php

use App\Models\MahjMatch;
use App\Models\User;
use App\Services\MahjNotificationService;
use App\Services\MatchChatService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;

Artisan::command('mahj:admin {email} {--name=Mahj Admin}', function (): int {
    $email = Str::lower(trim((string) $this->argument('email')));
    $password = $this->secret('Admin password');

    if ($password === null || strlen($password) < 8) {
        $this->error('Password must contain at least 8 characters.');

        return 1;
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

    return 0;
})->purpose('Create or update a Mahj admin account');

Artisan::command('mahj:chat-reminders', function (): int {
    $chat = app(MatchChatService::class);
    $notifications = app(MahjNotificationService::class);
    $start = now()->addMinutes(55);
    $end = now()->addMinutes(65);

    $matches = MahjMatch::query()
        ->whereIn('status', ['open', 'confirmed'])
        ->whereBetween('starts_at', [$start, $end])
        ->whereHas('players')
        ->get();

    foreach ($matches as $match) {
        $chat->gameReminder($match);
        $notifications->gameReminder($match);
    }

    $this->info('Chat reminders checked for '.$matches->count().' match(es).');

    return 0;
})->purpose('Add one-hour match reminders to active match chats');

Schedule::command('mahj:chat-reminders')
    ->everyFiveMinutes()
    ->withoutOverlapping();
