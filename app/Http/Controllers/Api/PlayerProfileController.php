<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MatchResource;
use App\Models\MahjMatch;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlayerProfileController extends Controller
{
    public function show(Request $request, User $user): JsonResponse
    {
        /** @var User $viewer */
        $viewer = $request->user();
        abort_if($viewer->id === $user->id, 404, 'Player not found.');
        abort_if($user->is_suspended, 404, 'Player not found.');

        // Blocking must never reveal either account's private relationship.
        abort_if(
            UserBlock::query()
                ->where(fn (Builder $query): Builder => $query
                    ->where('blocker_user_id', $viewer->id)
                    ->where('blocked_user_id', $user->id))
                ->orWhere(fn (Builder $query): Builder => $query
                    ->where('blocker_user_id', $user->id)
                    ->where('blocked_user_id', $viewer->id))
                ->exists(),
            404,
            'Player not found.',
        );

        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:30'],
        ]);
        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 2);

        // Mutual games are those joined by BOTH users, including future
        // scheduled matches. Invitations alone do not count as joined;
        // cancelled games are excluded. This must match FavoritePlayerController.
        $mutual = MahjMatch::query()
            ->with(['host', 'players', 'sport'])
            ->withCount('players')
            ->whereHas('players', fn (Builder $query): Builder => $query->whereKey($viewer->id))
            ->whereHas('players', fn (Builder $query): Builder => $query->whereKey($user->id))
            ->where('status', '!=', 'cancelled')
            ->whereDoesntHave(
                'host.blockedUsers',
                fn (Builder $query): Builder => $query->where('blocked_user_id', $viewer->id),
            )
            ->orderByDesc('starts_at')
            ->paginate($perPage, ['*'], 'page', $page);

        // Live statistics: future bookings and cancelled games are not
        // treated as games actually played or hosted.
        $played = $user->joinedMatches()->pastNonCancelled()->count();
        $hosted = $user->hostedMatches()->pastNonCancelled()->count();
        $completed = $user->joinedMatches()
            ->pastNonCancelled()
            ->where('status', 'completed')
            ->count();

        // Currently a completion-based estimate, not verified check-in
        // attendance. The database does not yet record presence per player.

        return response()->json([
            'player' => [
                'id' => (string) $user->id,
                'name' => $user->name,
                'avatar_url' => $user->avatarUrl($request->getSchemeAndHttpHost()),
                'matches_played' => $played,
                'matches_hosted' => $hosted,
                'attendance_percent' => $played > 0
                    ? (int) min(100, round(100 * $completed / $played))
                    : 0,
                'member_since_year' => $user->created_at?->year,
                'mutual_games_total' => $mutual->total(),
            ],
            'mutual_games' => MatchResource::collection(collect($mutual->items()))->resolve($request),
            'meta' => [
                'page' => $mutual->currentPage(),
                'last_page' => $mutual->lastPage(),
                'has_more' => $mutual->hasMorePages(),
            ],
        ]);
    }
}
