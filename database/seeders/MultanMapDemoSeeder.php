<?php

namespace Database\Seeders;

use App\Models\MahjMatch;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class MultanMapDemoSeeder extends Seeder
{
    private const TARGET_EMAIL = 'viewer@mahj.test';
    private const MATCH_COUNT = 100;
    private const CENTER_LATITUDE = 30.1575000;
    private const CENTER_LONGITUDE = 71.5249000;

    public function run(): void
    {
        $viewer = User::query()
            ->where('email', self::TARGET_EMAIL)
            ->first();

        if ($viewer === null) {
            $this->command?->warn(
                'Multan map demo data skipped: '.self::TARGET_EMAIL.' does not exist.',
            );

            return;
        }

        $sports = Sport::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($sports->isEmpty()) {
            $this->call(SportsCatalogSeeder::class);

            $sports = Sport::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
        }

        if ($sports->isEmpty()) {
            $this->command?->warn('Multan map demo data skipped: no active sports exist.');

            return;
        }

        $hosts = collect(range(1, 10))->map(function (int $number): User {
            $email = sprintf('multan.map.host%02d@mahj.test', $number);

            return User::query()->firstOrCreate(
                ['email' => $email],
                [
                    'name' => 'Multan Player '.$number,
                    'password' => Hash::make('MahjDemoOnly123!'),
                    'city' => 'Multan',
                    'state' => 'Punjab',
                    'email_verified_at' => now(),
                    'is_admin' => false,
                    'is_suspended' => false,
                ],
            );
        });

        for ($index = 0; $index < self::MATCH_COUNT; $index++) {
            $matchNumber = $index + 1;
            $host = $hosts[$index % $hosts->count()];
            $sport = $sports[$index % $sports->count()];

            [$latitude, $longitude] = $this->coordinatesFor($index);

            // Start seven days from now so none of the seeded matches disappear
            // after only two or three days. The full set stays populated for
            // more than three months after a single seeder run.
            $startsAt = now()
                ->addDays(7 + $index)
                ->setTime(17 + ($index % 5), ($index % 4) * 15);

            $match = MahjMatch::query()->updateOrCreate(
                [
                    'host_user_id' => $host->id,
                    'venue_name' => sprintf('Multan Map Demo %03d', $matchNumber),
                ],
                [
                    'name' => $sport->name,
                    'sport_id' => $sport->id,
                    'custom_sport_name' => null,
                    'location_address' => $this->locationLabelFor($index),
                    'starts_at' => $startsAt,
                    'is_public' => true,
                    'is_invite_only' => false,
                    'status' => 'open',
                    'is_featured' => $index < 10,
                    'featured_order' => $index < 10 ? $index + 1 : 0,
                    'max_players' => 4,
                    'notes' => 'Persistent Multan-area demo match for map and nearby-match UI testing.',
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'cancelled_at' => null,
                    'completed_at' => null,
                ],
            );

            // Keep the host as the only joined player so viewer@mahj.test can
            // discover and join every demo match.
            $match->players()->sync([
                $host->id => ['joined_at' => now()],
            ]);
        }

        $this->command?->info(
            self::MATCH_COUNT.' persistent nearby demo matches seeded around Multan for '.self::TARGET_EMAIL.'.',
        );
    }

    private function coordinatesFor(int $index): array
    {
        // Deterministic spread within roughly 1.25-8 miles of Multan city
        // centre, keeping all markers inside the app's normal nearby radius.
        $radiusMiles = 1.25 + (($index * 37) % 68) / 10;
        $angleRadians = deg2rad(fmod($index * 137.508, 360.0));

        $latitudeOffset = ($radiusMiles / 69.0) * cos($angleRadians);
        $longitudeMilesPerDegree = 69.0 * cos(deg2rad(self::CENTER_LATITUDE));
        $longitudeOffset = ($radiusMiles / $longitudeMilesPerDegree) * sin($angleRadians);

        return [
            round(self::CENTER_LATITUDE + $latitudeOffset, 7),
            round(self::CENTER_LONGITUDE + $longitudeOffset, 7),
        ];
    }

    private function locationLabelFor(int $index): string
    {
        $areas = [
            'Gulgasht Colony, Multan',
            'Bosan Road, Multan',
            'Cantt, Multan',
            'Shah Rukn-e-Alam, Multan',
            'Mumtazabad, Multan',
            'Vehari Road, Multan',
            'New Multan, Multan',
            'Wapda Town, Multan',
            'Model Town, Multan',
            'Northern Bypass, Multan',
        ];

        return $areas[$index % count($areas)].', Punjab, Pakistan';
    }
}
