<?php

namespace Database\Seeders;

use App\Models\MahjMatch;
use App\Models\MatchChatMessage;
use App\Models\User;
use Illuminate\Database\Seeder;

class M6ChatDemoSeeder extends Seeder
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
                'M6 chat demo data skipped: no existing account starts with '.
                self::TARGET_EMAIL_PREFIX.'.',
            );

            return;
        }

        $match = MahjMatch::query()
            ->whereIn('status', ['open', 'confirmed'])
            ->whereHas('players', fn ($query) => $query->whereKey($target->id))
            ->with('players')
            ->orderBy('starts_at')
            ->first();

        if ($match === null) {
            $this->command?->warn(
                'M6 chat demo data skipped: target account has no active match.',
            );

            return;
        }

        $other = $match->players->firstWhere('id', '!=', $target->id);

        MatchChatMessage::query()->firstOrCreate(
            [
                'match_id' => $match->id,
                'event_key' => 'm6-demo-welcome',
            ],
            [
                'sender_user_id' => null,
                'type' => 'system',
                'body' => 'Match chat is ready',
            ],
        );

        if ($other !== null) {
            MatchChatMessage::query()->firstOrCreate(
                [
                    'match_id' => $match->id,
                    'event_key' => 'm6-demo-other-message',
                ],
                [
                    'sender_user_id' => $other->id,
                    'type' => 'message',
                    'body' => 'Looking forward to the match.',
                ],
            );
        }

        MatchChatMessage::query()->firstOrCreate(
            [
                'match_id' => $match->id,
                'event_key' => 'm6-demo-target-message',
            ],
            [
                'sender_user_id' => $target->id,
                'type' => 'message',
                'body' => 'See you there.',
            ],
        );

        $this->command?->info(
            'M6 chat demo data ready for '.$target->email.'.',
        );
    }
}
