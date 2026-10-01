<?php

namespace Database\Seeders;

use App\Models\MahjMatch;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Database\Seeder;

class M8CompletedMatchesDemoSeeder extends Seeder
{
    private const TARGET_EMAIL_PREFIX = 'map.view';

    public function run(): void
    {
        $target = User::query()
            ->where('email', 'like', self::TARGET_EMAIL_PREFIX.'%')
            ->orderBy('id')
            ->first();

        if ($target === null) {
            $this->command?->warn(
                'M8 completed-match demo data skipped: no existing account starts with '.
                self::TARGET_EMAIL_PREFIX.'.',
            );

            return;
        }

        $sport = Sport::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        if ($sport === null) {
            $this->command?->warn('M8 completed-match demo data skipped: no active sport exists.');

            return;
        }

        $others = User::query()
            ->where('id', '!=', $target->id)
            ->where('is_admin', false)
            ->orderBy('id')
            ->limit(3)
            ->get();

        if ($others->isEmpty()) {
            $this->command?->warn('M8 completed-match demo data skipped: no other users exist.');

            return;
        }

        $completed = MahjMatch::query()->updateOrCreate(
            [
                'host_user_id' => $target->id,
                'venue_name' => 'M8 Completed Match Demo',
            ],
            [
                'name' => $sport->name,
                'sport_id' => $sport->id,
                'custom_sport_name' => null,
                'location_address' => 'Multan, Punjab, Pakistan',
                'starts_at' => now()->subHours(2),
                'is_public' => true,
                'is_invite_only' => false,
                'status' => 'completed',
                'max_players' => 4,
                'notes' => 'Completed match ready for M8 score testing.',
                'latitude' => 30.1575000,
                'longitude' => 71.5249000,
                'cancelled_at' => null,
                'completed_at' => now(),
            ],
        );

        $completedPlayerIds = collect([$target->id])
            ->merge($others->pluck('id'))
            ->take(4)
            ->values();

        $completed->players()->sync(
            $completedPlayerIds->mapWithKeys(fn (int $userId): array => [
                $userId => ['joined_at' => now()->subHours(3)],
            ])->all(),
        );

        $completed->scores()->delete();

        $cancelled = MahjMatch::query()->updateOrCreate(
            [
                'host_user_id' => $target->id,
                'venue_name' => 'M8 Cancelled Match Demo',
            ],
            [
                'name' => $sport->name,
                'sport_id' => $sport->id,
                'custom_sport_name' => null,
                'location_address' => 'Multan, Punjab, Pakistan',
                'starts_at' => now()->subDay(),
                'is_public' => true,
                'is_invite_only' => false,
                'status' => 'cancelled',
                'max_players' => 4,
                'notes' => 'Cancelled match for My Matches history testing.',
                'latitude' => 30.1575000,
                'longitude' => 71.5249000,
                'cancelled_at' => now()->subHours(20),
                'completed_at' => null,
            ],
        );

        $cancelled->players()->sync([
            $target->id => ['joined_at' => now()->subDays(2)],
        ]);

        $this->command?->info(
            'M8 completed/cancelled demo matches ready for '.$target->email.'.',
        );
    }
}
