<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserBlock;
use App\Models\UserReport;
use Illuminate\Database\Seeder;

class M9SafetyDemoSeeder extends Seeder
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
                'M9 safety demo data skipped: no existing account starts with '.
                self::TARGET_EMAIL_PREFIX.'.',
            );

            return;
        }

        $others = User::query()
            ->where('id', '!=', $target->id)
            ->where('is_admin', false)
            ->orderBy('id')
            ->limit(2)
            ->get();

        if ($others->isEmpty()) {
            $this->command?->warn('M9 safety demo data skipped: no other users exist.');

            return;
        }

        $first = $others->first();

        UserBlock::query()->updateOrCreate(
            [
                'blocker_user_id' => $target->id,
                'blocked_user_id' => $first->id,
            ],
            [
                'reason' => 'safety_concern',
            ],
        );

        UserReport::query()->updateOrCreate(
            [
                'reporter_user_id' => $target->id,
                'reported_user_id' => $first->id,
                'reason' => 'inappropriate_behavior',
                'notes' => 'M9 pending report demo.',
            ],
            [
                'status' => 'pending',
                'reviewed_at' => null,
            ],
        );

        $second = $others->skip(1)->first();
        if ($second !== null) {
            UserReport::query()->updateOrCreate(
                [
                    'reporter_user_id' => $target->id,
                    'reported_user_id' => $second->id,
                    'reason' => 'safety_concern',
                    'notes' => 'M9 closed report demo.',
                ],
                [
                    'status' => 'closed',
                    'reviewed_at' => now()->subHour(),
                ],
            );
        }

        $this->command?->info(
            'M9 safety demo data ready for '.$target->email.'.',
        );
    }
}
