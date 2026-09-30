<?php

namespace Database\Seeders;

use App\Models\MahjMatch;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MatchesDemoSeeder extends Seeder
{
    public function run(): void
    {
        $users = collect([
            ['name' => 'Austen Parker', 'email' => 'demo.austen@mahj.test'],
            ['name' => 'Alex Turner', 'email' => 'demo.alex@mahj.test'],
            ['name' => 'Jenny Wilson', 'email' => 'demo.jenny@mahj.test'],
            ['name' => 'Daniel Carter', 'email' => 'demo.daniel@mahj.test'],
            ['name' => 'Sophia Brooks', 'email' => 'demo.sophia@mahj.test'],
            ['name' => 'Marcus Reed', 'email' => 'demo.marcus@mahj.test'],
        ])->mapWithKeys(function (array $profile): array {
            $user = User::query()->firstOrCreate(
                ['email' => $profile['email']],
                [
                    'name' => $profile['name'],
                    'email_verified_at' => now(),
                    'password' => Hash::make(Str::random(48)),
                ],
            );

            return [$profile['email'] => $user];
        });

        $definitions = [
            [
                'host' => 'demo.austen@mahj.test',
                'venue_name' => 'Central Park View',
                'location_address' => 'Central Park, New York, NY',
                'starts_at' => now()->addDay()->setTime(18, 0),
                'notes' => 'Friendly game. Please arrive 10 minutes early.',
                'status' => 'open',
                'players' => ['demo.austen@mahj.test'],
            ],
            [
                'host' => 'demo.alex@mahj.test',
                'venue_name' => 'Riverside Court',
                'location_address' => 'Riverside Park, New York, NY',
                'starts_at' => now()->addDay()->setTime(19, 30),
                'notes' => 'Casual match. All levels welcome.',
                'status' => 'open',
                'players' => [
                    'demo.alex@mahj.test',
                    'demo.jenny@mahj.test',
                ],
            ],
            [
                'host' => 'demo.jenny@mahj.test',
                'venue_name' => 'Downtown Sports Center',
                'location_address' => 'Downtown, New York, NY',
                'starts_at' => now()->addDays(2)->setTime(17, 30),
                'notes' => 'One opening left.',
                'status' => 'open',
                'players' => [
                    'demo.jenny@mahj.test',
                    'demo.daniel@mahj.test',
                    'demo.sophia@mahj.test',
                ],
            ],
            [
                'host' => 'demo.daniel@mahj.test',
                'venue_name' => 'Sunset Community Court',
                'location_address' => 'West Side, New York, NY',
                'starts_at' => now()->addDays(2)->setTime(20, 0),
                'notes' => 'Confirmed four-player match.',
                'status' => 'confirmed',
                'players' => [
                    'demo.daniel@mahj.test',
                    'demo.austen@mahj.test',
                    'demo.sophia@mahj.test',
                    'demo.marcus@mahj.test',
                ],
            ],
            [
                'host' => 'demo.sophia@mahj.test',
                'venue_name' => 'East Side Courts',
                'location_address' => 'East Side, New York, NY',
                'starts_at' => now()->addDays(3)->setTime(18, 30),
                'notes' => 'Open match with two spots available.',
                'status' => 'open',
                'players' => [
                    'demo.sophia@mahj.test',
                    'demo.marcus@mahj.test',
                ],
            ],
        ];

        foreach ($definitions as $definition) {
            /** @var User $host */
            $host = $users->get($definition['host']);

            $match = MahjMatch::query()->updateOrCreate(
                [
                    'host_user_id' => $host->id,
                    'venue_name' => $definition['venue_name'],
                ],
                [
                    'location_address' => $definition['location_address'],
                    'starts_at' => $definition['starts_at'],
                    'is_public' => true,
                    'is_invite_only' => false,
                    'status' => $definition['status'],
                    'max_players' => 4,
                    'notes' => $definition['notes'],
                    'cancelled_at' => null,
                ],
            );

            $playerIds = collect($definition['players'])
                ->map(fn (string $email): int => $users->get($email)->id)
                ->all();

            $syncData = collect($playerIds)
                ->mapWithKeys(fn (int $userId): array => [
                    $userId => ['joined_at' => now()],
                ])
                ->all();

            $match->players()->sync($syncData);
        }
    }
}
