<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserDeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['required', 'in:android,ios'],
        ]);

        /** @var User $user */
        $user = $request->user();

        UserDeviceToken::query()->updateOrCreate(
            ['token' => $validated['token']],
            [
                'user_id' => $user->id,
                'platform' => $validated['platform'],
                'last_seen_at' => now(),
            ],
        );

        return response()->json(['message' => 'Device token registered.']);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:512'],
        ]);

        /** @var User $user */
        $user = $request->user();

        UserDeviceToken::query()
            ->where('user_id', $user->id)
            ->where('token', $validated['token'])
            ->delete();

        return response()->json(['message' => 'Device token removed.']);
    }
}
