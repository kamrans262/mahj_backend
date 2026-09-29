<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
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
