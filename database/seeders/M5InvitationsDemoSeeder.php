<?php

namespace Database\Seeders;

use App\Models\MahjMatch;
use App\Models\MatchInvitation;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class M5InvitationsDemoSeeder extends Seeder
{
    private const TARGET_EMAIL = 'kobiral702@abowned.com';

    public function run(): void
    {
        $target = User::query()
            ->where('email', self::TARGET_EMAIL)
            ->first();

        if ($target === null) {
            $this->command?->warn(
                'M5 demo data skipped: '.self::TARGET_EMAIL.' does not exist.',
            );

            return;
        }

        $helpers = collect([
            ['name' => 'M5 Austen Parker', 'email' => 'm5.austen@mahj.test'],
            ['name' => 'M5 Alex Turner', 'email' => 'm5.alex@mahj.test'],
            ['name' => 'M5 Jenny Wilson', 'email' => 'm5.jenny@mahj.test'],
            ['name' => 'M5 Daniel Carter', 'email' => 'm5.daniel@mahj.test'],
            ['name' => 'M5 Sophia Brooks', 'email' => 'm5.sophia@mahj.test'],
            ['name' => 'M5 Marcus Reed', 'email' => 'm5.marcus@mahj.test'],
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

        $this->seedCreatedByMe($target, $helpers, $sports);
        $this->seedUpcoming($target, $helpers, $sports);
        $this->seedPendingInvite($target, $helpers, $sports);
        $this->seedFullInvite($target, $helpers, $sports);
        $this->seedAcceptedHistory($target, $helpers, $sports);
        $this->seedDeclinedHistory($target, $helpers, $sports);

        $this->command?->info(
            'M5 demo data ready for '.self::TARGET_EMAIL.'.',
        );
    }

    private function seedCreatedByMe(
        User $target,
        $helpers,
        $sports,
    ): void {
        $match = $this->upsertDemoMatch(
            host: $target,
            sport: $sports->get('basketball'),
            marker: '[M5 DEMO] Created by me',
            venueName: 'DHA Multan Basketball Court',
            locationAddress: 'DHA Multan, Punjab, Pakistan',
            latitude: 30.2397000,
            longitude: 71.4518000,
            startsAt: now()->addDay()->setTime(18, 30),
            status: 'open',
            isInviteOnly: false,
        );

        $this->syncPlayers($match, [
            $target,
            $helpers->get('m5.marcus@mahj.test'),
        ]);
    }

    private function seedUpcoming(
        User $target,
        $helpers,
        $sports,
    ): void {
        $host = $helpers->get('m5.austen@mahj.test');

        $match = $this->upsertDemoMatch(
            host: $host,
            sport: $sports->get('soccer'),
            marker: '[M5 DEMO] Upcoming joined match',
            venueName: 'Gulgasht Community Ground',
            locationAddress: 'Gulgasht Colony, Multan, Punjab, Pakistan',
            latitude: 30.2197200,
            longitude: 71.4711100,
            startsAt: now()->addDays(2)->setTime(19, 0),
            status: 'open',
            isInviteOnly: false,
        );

        $this->syncPlayers($match, [
            $host,
            $target,
            $helpers->get('m5.jenny@mahj.test'),
        ]);
    }

    private function seedPendingInvite(
        User $target,
        $helpers,
        $sports,
    ): void {
        $host = $helpers->get('m5.alex@mahj.test');

        $match = $this->upsertDemoMatch(
            host: $host,
            sport: $sports->get('american-football'),
            marker: '[M5 DEMO] Pending invitation',
            venueName: 'Multan Cantt Sports Court',
            locationAddress: 'Multan Cantt, Multan, Punjab, Pakistan',
            latitude: 30.1800000,
            longitude: 71.4600000,
            startsAt: now()->addDays(3)->setTime(18, 0),
            status: 'open',
            isInviteOnly: true,
        );

        $this->syncPlayers($match, [
            $host,
            $helpers->get('m5.sophia@mahj.test'),
        ]);

        $this->upsertInvitation(
            match: $match,
            inviter: $host,
            invitee: $target,
            status: 'pending',
        );
    }

    private function seedFullInvite(
        User $target,
        $helpers,
        $sports,
    ): void {
        $host = $helpers->get('m5.daniel@mahj.test');

        $match = $this->upsertDemoMatch(
            host: $host,
            sport: $sports->get('tennis'),
            marker: '[M5 DEMO] Full invitation',
            venueName: 'BZU Sports Ground',
            locationAddress: 'Bahauddin Zakariya University, Multan, Punjab, Pakistan',
            latitude: 30.2663800,
            longitude: 71.5082500,
            startsAt: now()->addDays(4)->setTime(17, 30),
            status: 'confirmed',
            isInviteOnly: true,
        );

        $this->syncPlayers($match, [
            $host,
            $helpers->get('m5.austen@mahj.test'),
            $helpers->get('m5.jenny@mahj.test'),
            $helpers->get('m5.marcus@mahj.test'),
        ]);

        $this->upsertInvitation(
            match: $match,
            inviter: $host,
            invitee: $target,
            status: 'pending',
        );
    }

    private function seedAcceptedHistory(
        User $target,
        $helpers,
        $sports,
    ): void {
        $host = $helpers->get('m5.sophia@mahj.test');

        $match = $this->upsertDemoMatch(
            host: $host,
            sport: $sports->get('basketball'),
            marker: '[M5 DEMO] Accepted invitation history',
            venueName: 'New Multan Sports Arena',
            locationAddress: 'New Multan, Multan, Punjab, Pakistan',
            latitude: 30.1670000,
            longitude: 71.5420000,
            startsAt: now()->addDays(5)->setTime(20, 0),
            status: 'open',
            isInviteOnly: true,
        );

        $this->syncPlayers($match, [
            $host,
            $target,
        ]);

        $this->upsertInvitation(
            match: $match,
            inviter: $host,
            invitee: $target,
            status: 'accepted',
        );
    }

    private function seedDeclinedHistory(
        User $target,
        $helpers,
        $sports,
    ): void {
        $host = $helpers->get('m5.marcus@mahj.test');

        $match = $this->upsertDemoMatch(
            host: $host,
            sport: $sports->get('soccer'),
            marker: '[M5 DEMO] Declined invitation history',
            venueName: 'Multan Public Sports Ground',
            locationAddress: 'Multan, Punjab, Pakistan',
            latitude: 30.1575000,
            longitude: 71.5249000,
            startsAt: now()->addDays(6)->setTime(18, 45),
            status: 'open',
            isInviteOnly: true,
        );

        $this->syncPlayers($match, [$host]);

        $this->upsertInvitation(
            match: $match,
            inviter: $host,
            invitee: $target,
            status: 'declined',
        );
    }

    private function upsertDemoMatch(
        User $host,
        ?Sport $sport,
        string $marker,
        string $venueName,
        string $locationAddress,
        float $latitude,
        float $longitude,
        $startsAt,
        string $status,
        bool $isInviteOnly,
    ): MahjMatch {
        if ($sport === null) {
            throw new \RuntimeException(
                'Sports catalog must be seeded before M5 demo data.',
            );
        }

        $match = MahjMatch::query()->firstOrNew([
            'host_user_id' => $host->id,
            'notes' => $marker,
        ]);

        $match->fill([
            'name' => $sport->name,
            'sport_id' => $sport->id,
            'custom_sport_name' => null,
            'venue_name' => $venueName,
            'location_address' => $locationAddress,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'starts_at' => $startsAt,
            'is_public' => !$isInviteOnly,
            'is_invite_only' => $isInviteOnly,
            'status' => $status,
            'is_featured' => false,
            'featured_order' => 0,
            'max_players' => 4,
            'notes' => $marker,
            'cancelled_at' => null,
        ]);
        $match->save();

        return $match;
    }

    private function syncPlayers(MahjMatch $match, array $players): void
    {
        $syncData = collect($players)
            ->filter()
            ->unique('id')
            ->mapWithKeys(fn (User $user): array => [
                $user->id => ['joined_at' => now()],
            ])
            ->all();

        $match->players()->sync($syncData);
    }

    private function upsertInvitation(
        MahjMatch $match,
        User $inviter,
        User $invitee,
        string $status,
    ): void {
        MatchInvitation::query()->updateOrCreate(
            [
                'match_id' => $match->id,
                'invitee_user_id' => $invitee->id,
            ],
            [
                'inviter_user_id' => $inviter->id,
                'status' => $status,
                'responded_at' => $status === 'pending' ? null : now(),
            ],
        );
    }
}
