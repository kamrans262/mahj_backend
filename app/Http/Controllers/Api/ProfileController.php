<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function uploadAvatar(Request $request): UserResource
    {
        $validated = $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
        ]);

        $user = $request->user();
        $path = $validated['avatar']->store('avatars', 'public');
        $oldPath = $user->avatar_path;
        $user->update(['avatar_path' => $path]);

        if ($oldPath && ! str_starts_with($oldPath, 'http')) {
            Storage::disk('public')->delete($oldPath);
        }

        return new UserResource($user->refresh());
    }

    public function removeAvatar(Request $request): UserResource
    {
        $user = $request->user();
        $oldPath = $user->avatar_path;
        $user->update(['avatar_path' => null]);

        if ($oldPath && ! str_starts_with($oldPath, 'http')) {
            Storage::disk('public')->delete($oldPath);
        }

        return new UserResource($user->refresh());
    }

    public function show(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function update(Request $request): UserResource
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'zip_code' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'bio' => ['nullable', 'string', 'max:1000'],
        ]);

        $request->user()->update([
            'name' => trim($validated['name']),
            'phone' => trim((string) ($validated['phone'] ?? '')),
            'zip_code' => trim((string) ($validated['zip_code'] ?? '')),
            'city' => trim((string) ($validated['city'] ?? '')),
            'state' => trim((string) ($validated['state'] ?? '')),
            'bio' => trim((string) ($validated['bio'] ?? '')),
        ]);

        return new UserResource($request->user()->refresh());
    }
}
