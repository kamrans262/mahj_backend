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
        $sportName = $this->sport?->name
            ?? $this->custom_sport_name
            ?? $this->name;
        $bannerImageUrl = $this->sport?->banner_image_path
            ? rtrim($request->getSchemeAndHttpHost(), '/').'/storage/'.ltrim($this->sport->banner_image_path, '/')
            : null;
        $distance = $this->getAttribute('distance_miles');

        return [
            'id' => (string) $this->id,
            'name' => $sportName,
            'sport_name' => $sportName,
            'sport' => $this->sport === null ? null : [
                'id' => (string) $this->sport->id,
                'name' => $this->sport->name,
                'slug' => $this->sport->slug,
                'icon_key' => $this->sport->icon_key,
                'banner_image_url' => $bannerImageUrl,
            ],
            'custom_sport_name' => $this->custom_sport_name,
            'sport_icon_key' => $this->sport?->icon_key ?? 'generic',
            'banner_image_url' => $bannerImageUrl,
            'is_featured' => $this->is_featured,
            'featured_order' => $this->featured_order,
            'location' => $this->location_address,
            'location_address' => $this->location_address,
            'venue_name' => $this->venue_name,
            'starts_at' => $this->starts_at?->toISOString(),
            'current_players' => $currentPlayers,
            'max_players' => $this->max_players,
            'status' => $this->status,
            'completed_at' => $this->completed_at?->toISOString(),
            'is_public' => $this->is_public,
            'is_invite_only' => $this->is_invite_only,
            'notes' => $this->notes,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'distance_miles' => is_numeric($distance) ? (float) $distance : null,
            'host' => [
                'id' => (string) $this->host_user_id,
                'name' => $this->host?->name ?? 'Host',
                'avatar_url' => $this->host?->avatarUrl(
                    $request->getSchemeAndHttpHost(),
                ),
            ],
            'players' => $this->whenLoaded('players', fn () => $this->players
                ->map(fn (User $player) => [
                    'id' => (string) $player->id,
                    'name' => $player->name,
                    'avatar_url' => $player->avatarUrl(
                        $request->getSchemeAndHttpHost(),
                    ),
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
            'can_complete' => $isHost
                && in_array($this->status, ['open', 'confirmed'], true)
                && $this->starts_at !== null
                && $this->starts_at->lte(now()),
        ];
    }
}
