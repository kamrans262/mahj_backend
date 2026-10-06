<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone ?? '',
            'zip_code' => $this->zip_code ?? '',
            'city' => $this->city ?? '',
            'state' => $this->state ?? '',
            'bio' => $this->bio ?? '',
            'avatar_url' => $this->resource->avatarUrl(
                $request->getSchemeAndHttpHost(),
            ),
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'upcoming_match_count' => 0,
            'completed_match_count' => $this->resource->joinedMatches()->where('status', 'completed')->count(),
            'unread_notification_count' => $this->resource->userNotifications()->whereNull('read_at')->count(),
            'unread_message_count' => 0,
            'google_connected' => filled($this->google_id),
            'apple_connected' => false,
        ];
    }
}
