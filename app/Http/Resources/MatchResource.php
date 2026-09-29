<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\MahjMatch */
class MatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var User|null $user */
        $user = $request->user();
        $playerIds = $this->relationLoaded('players')
            ? $this->players->pluck('id')
            : collect();
        $currentPlayers = $this->players_count
            ?? ($this->relationLoaded('players') ? $this->players->count() : 0);
        $isHost = $user !== null && $this->host_user_id === $user->id;
        $isJoined = $user !== null && $playerIds->contains($user->id);
        $isFull = $currentPlayers >= $this->max_players;

        return [
            'id' => (string) $this->id,
            'sport_name' => 'Game',
            'location' => $this->location_address,
            'location_address' => $this->location_address,
            'venue_name' => $this->venue_name,
            'starts_at' => $this->starts_at?->toISOString(),
            'current_players' => $currentPlayers,
            'max_players' => $this->max_players,
            'status' => $this->status,
            'is_public' => $this->is_public,
            'is_invite_only' => $this->is_invite_only,
            'notes' => $this->notes,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'host' => [
                'id' => (string) $this->host_user_id,
                'name' => $this->host?->name ?? 'Host',
            ],
            'players' => $this->whenLoaded('players', fn () => $this->players
                ->map(fn (User $player) => [
                    'id' => (string) $player->id,
                    'name' => $player->name,
                ])
                ->values()),
            'is_joined' => $isJoined,
            'is_host' => $isHost,
            'can_join' => ! $isJoined
                && ! $isHost
                && $this->is_public
                && ! $this->is_invite_only
                && $this->status === 'open'
                && ! $isFull,
            'can_leave' => $isJoined
                && ! $isHost
                && ! in_array($this->status, ['cancelled', 'completed'], true),
            'can_cancel' => $isHost
                && ! in_array($this->status, ['cancelled', 'completed'], true),
        ];
    }
}
