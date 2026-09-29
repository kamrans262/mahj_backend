<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

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
            'avatar_url' => $this->avatar_path
                ? Storage::disk('public')->url($this->avatar_path)
                : null,
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'upcoming_match_count' => 0,
            'completed_match_count' => 0,
            'unread_notification_count' => 0,
            'unread_message_count' => 0,
            'google_connected' => false,
            'apple_connected' => false,
        ];
    }
}
