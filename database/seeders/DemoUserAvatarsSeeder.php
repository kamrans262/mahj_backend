<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DemoUserAvatarsSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::query()
            ->where(function ($query): void {
                $query->where('email', 'like', '%@mahj.test')
                    ->orWhere('email', 'test@example.com');
            })
            ->orderBy('id')
            ->get();

        foreach ($users as $user) {
            $seed = rawurlencode($user->email);

            $user->update([
                'avatar_path' => "https://api.dicebear.com/10.x/adventurer/png?seed={$seed}&size=128",
            ]);
        }

        $this->command?->info(
            'Demo avatars assigned to '.$users->count().' demo users.',
        );
    }
}
