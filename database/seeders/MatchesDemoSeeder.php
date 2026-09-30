<?php

namespace Database\Seeders;

use App\Models\MahjMatch;
use App\Models\Sport;
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

        $sports = Sport::query()->get()->keyBy('slug');

        $definitions = [
            [
                'host' => 'demo.austen@mahj.test',
                'sport_slug' => 'basketball',
                'venue_name' => 'Multan City Sports Court',
                'location_address' => 'Multan, Punjab, Pakistan',
                'latitude' => 30.1575000,
                'longitude' => 71.5249000,
                'starts_at' => now()->addMinutes(45),
                'notes' => 'Friendly game. Please arrive 10 minutes early.',
                'status' => 'open',
                'is_featured' => true,
                'featured_order' => 10,
                'players' => ['demo.austen@mahj.test'],
            ],
            [
                'host' => 'demo.alex@mahj.test',
                'sport_slug' => 'american-football',
                'venue_name' => 'Multan Cantt Sports Court',
                'location_address' => 'Multan Cantt, Multan, Punjab, Pakistan',
                'latitude' => 30.1800000,
                'longitude' => 71.4600000,
                'starts_at' => now()->addDay()->setTime(19, 30),
                'notes' => 'Casual match. All levels welcome.',
                'status' => 'open',
                'is_featured' => true,
                'featured_order' => 20,
                'players' => [
                    'demo.alex@mahj.test',
                    'demo.jenny@mahj.test',
                ],
            ],
            [
                'host' => 'demo.jenny@mahj.test',
                'sport_slug' => 'tennis',
                'venue_name' => 'Gulgasht Sports Ground',
                'location_address' => 'Gulgasht Colony, Multan, Punjab, Pakistan',
                'latitude' => 30.2197200,
                'longitude' => 71.4711100,
                'starts_at' => now()->addDays(2)->setTime(17, 30),
                'notes' => 'One opening left.',
                'status' => 'open',
                'is_featured' => false,
                'featured_order' => 0,
                'players' => [
                    'demo.jenny@mahj.test',
                    'demo.daniel@mahj.test',
                    'demo.sophia@mahj.test',
                ],
            ],
            [
                'host' => 'demo.daniel@mahj.test',
                'sport_slug' => 'basketball',
                'venue_name' => 'BZU Sports Ground',
                'location_address' => 'Bahauddin Zakariya University, Multan, Punjab, Pakistan',
                'latitude' => 30.2663800,
                'longitude' => 71.5082500,
                'starts_at' => now()->addDays(2)->setTime(20, 0),
                'notes' => 'Confirmed four-player match.',
                'status' => 'confirmed',
                'is_featured' => false,
                'featured_order' => 0,
                'players' => [
                    'demo.daniel@mahj.test',
                    'demo.austen@mahj.test',
                    'demo.sophia@mahj.test',
                    'demo.marcus@mahj.test',
                ],
            ],
            [
                'host' => 'demo.sophia@mahj.test',
                'sport_slug' => 'soccer',
                'venue_name' => 'New Multan Community Ground',
                'location_address' => 'New Multan, Multan, Punjab, Pakistan',
                'latitude' => 30.1670000,
                'longitude' => 71.5420000,
                'starts_at' => now()->addDays(3)->setTime(18, 30),
                'notes' => 'Open match with two spots available.',
                'status' => 'open',
                'is_featured' => false,
                'featured_order' => 0,
                'players' => [
                    'demo.sophia@mahj.test',
                    'demo.marcus@mahj.test',
                ],
            ],
        ];

        foreach ($definitions as $definition) {
            /** @var User $host */
            $host = $users->get($definition['host']);

            /** @var Sport $sport */
            $sport = $sports->get($definition['sport_slug']);

            $match = MahjMatch::query()->updateOrCreate(
                [
                    'host_user_id' => $host->id,
                ],
                [
                    'name' => $sport->name,
                    'sport_id' => $sport->id,
                    'custom_sport_name' => null,
                    'venue_name' => $definition['venue_name'],
                    'location_address' => $definition['location_address'],
                    'latitude' => $definition['latitude'],
                    'longitude' => $definition['longitude'],
                    'starts_at' => $definition['starts_at'],
                    'is_public' => true,
                    'is_invite_only' => false,
                    'status' => $definition['status'],
                    'is_featured' => $definition['is_featured'],
                    'featured_order' => $definition['featured_order'],
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
