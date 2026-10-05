<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MatchResource;
use App\Models\MahjMatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MatchFavoriteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $matches = $user->favoriteMatches()
            ->with(['host', 'players', 'sport'])
            ->withCount('players')
            ->whereDoesntHave(
                'host.blockedUsers',
                fn (Builder $query): Builder => $query->where('blocked_user_id', $user->id),
            )
            ->orderByDesc('match_favorites.created_at')
            ->limit(100)
            ->get();

        $matches->each(fn (MahjMatch $match) => $match->setAttribute('is_favorite', true));

        return response()->json([
            'data' => MatchResource::collection($matches)->resolve($request),
        ]);
    }

    public function store(Request $request, MahjMatch $match): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->favoriteMatches()->syncWithoutDetaching([$match->id]);

        return response()->json([
            'message' => 'Match added to favorites.',
            'is_favorite' => true,
        ]);
    }

    public function destroy(Request $request, MahjMatch $match): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->favoriteMatches()->detach($match->id);

        return response()->json([
            'message' => 'Match removed from favorites.',
            'is_favorite' => false,
        ]);
    }
}
