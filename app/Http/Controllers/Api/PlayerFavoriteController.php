<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PlayerFavoriteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $request->user();

        $players = $currentUser->favoritePlayers()
            ->withCount('joinedMatches')
            ->where('is_suspended', false)
            ->orderByPivot('created_at', 'desc')
            ->limit(100)
            ->get();

        return response()->json([
            'data' => $players
                ->map(fn (User $user): array => $this->userData($request, $user))
                ->values(),
        ]);
    }

    public function status(Request $request, User $user): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $request->user();
        $this->ensureDifferentUsers($currentUser, $user);

        return response()->json([
            'is_favorite' => $currentUser->favoritePlayers()->whereKey($user->id)->exists(),
        ]);
    }

    public function store(Request $request, User $user): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $request->user();
        $this->ensureDifferentUsers($currentUser, $user);

        if ($user->is_suspended) {
            abort(404, 'Player not found.');
        }

        $currentUser->favoritePlayers()->syncWithoutDetaching([$user->id]);

        return response()->json([
            'message' => 'Player added to favorites.',
            'is_favorite' => true,
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $request->user();
        $this->ensureDifferentUsers($currentUser, $user);

        $currentUser->favoritePlayers()->detach($user->id);

        return response()->json([
            'message' => 'Player removed from favorites.',
            'is_favorite' => false,
        ]);
    }

    private function userData(Request $request, User $user): array
    {
        return [
            'id' => (string) $user->id,
            'name' => $user->name,
            'username' => Str::before($user->email, '@'),
            'avatar_url' => $user->avatarUrl($request->getSchemeAndHttpHost()),
            'games_count' => (int) ($user->joined_matches_count ?? 0),
            'is_favorite' => true,
        ];
    }

    private function ensureDifferentUsers(User $actor, User $target): void
    {
        if ($actor->id === $target->id) {
            throw ValidationException::withMessages([
                'user' => ['You cannot favorite your own account.'],
            ]);
        }
    }
}
