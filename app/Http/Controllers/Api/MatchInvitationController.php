<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MatchResource;
use App\Models\MahjMatch;
use App\Models\MatchInvitation;
use App\Models\User;
use App\Models\UserBlock;
use App\Services\MahjNotificationService;
use App\Services\MatchChatService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MatchInvitationController extends Controller
{
    public function __construct(
        private readonly MatchChatService $chat,
        private readonly MahjNotificationService $notifications,
    ) {
    }

    private const EARTH_RADIUS_MILES = 3958.7613;

    public function myMatches(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'upcoming_page' => ['nullable', 'integer', 'min:1'],
            'created_page' => ['nullable', 'integer', 'min:1'],
            'invites_page' => ['nullable', 'integer', 'min:1'],
            'completed_page' => ['nullable', 'integer', 'min:1'],
            'cancelled_page' => ['nullable', 'integer', 'min:1'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 20);
        $upcomingPage = (int) ($validated['upcoming_page'] ?? 1);
        $createdPage = (int) ($validated['created_page'] ?? 1);
        $invitesPage = (int) ($validated['invites_page'] ?? 1);
        $completedPage = (int) ($validated['completed_page'] ?? 1);
        $cancelledPage = (int) ($validated['cancelled_page'] ?? 1);
        $hasCoordinates = isset($validated['latitude'], $validated['longitude']);

        $upcomingPaginator = MahjMatch::query()
            ->with(['host', 'players', 'sport'])
            ->withCount('players')
            ->where('host_user_id', '!=', $user->id)
            ->whereDoesntHave(
                'host.blockedUsers',
                fn (Builder $query): Builder => $query->where('blocked_user_id', $user->id),
            )
            ->whereHas('players', fn (Builder $query): Builder => $query->whereKey($user->id))
            ->where('starts_at', '>=', now()->subHours(3))
            ->whereNotIn('status', ['cancelled', 'completed'])
            ->orderBy('starts_at')
            ->simplePaginate($perPage, ['*'], 'upcoming_page', $upcomingPage);

        $createdPaginator = MahjMatch::query()
            ->with(['host', 'players', 'sport'])
            ->withCount('players')
            ->where('host_user_id', $user->id)
            ->whereNotIn('status', ['cancelled', 'completed'])
            ->orderByDesc('starts_at')
            ->simplePaginate($perPage, ['*'], 'created_page', $createdPage);

        $completedPaginator = MahjMatch::query()
            ->with(['host', 'players', 'sport'])
            ->withCount('players')
            ->where('status', 'completed')
            ->where(function (Builder $query) use ($user): void {
                $query->where('host_user_id', $user->id)
                    ->orWhereHas('players', fn (Builder $query): Builder => $query->whereKey($user->id));
            })
            ->whereDoesntHave(
                'host.blockedUsers',
                fn (Builder $query): Builder => $query->where('blocked_user_id', $user->id),
            )
            ->orderByDesc('starts_at')
            ->simplePaginate($perPage, ['*'], 'completed_page', $completedPage);

        $cancelledPaginator = MahjMatch::query()
            ->with(['host', 'players', 'sport'])
            ->withCount('players')
            ->where('status', 'cancelled')
            ->where(function (Builder $query) use ($user): void {
                $query->where('host_user_id', $user->id)
                    ->orWhereHas('players', fn (Builder $query): Builder => $query->whereKey($user->id));
            })
            ->whereDoesntHave(
                'host.blockedUsers',
                fn (Builder $query): Builder => $query->where('blocked_user_id', $user->id),
            )
            ->orderByDesc('starts_at')
            ->simplePaginate($perPage, ['*'], 'cancelled_page', $cancelledPage);

        $invitationsPaginator = MatchInvitation::query()
            ->with([
                'inviter',
                'match.host',
                'match.players',
                'match.sport',
            ])
            ->where('invitee_user_id', $user->id)
            ->where('status', 'pending')
            ->whereHas('match', function (Builder $query) use ($user): void {
                $query->whereNotIn('status', ['cancelled', 'completed'])
                    ->where('starts_at', '>=', now()->subHours(3))
                    ->whereDoesntHave(
                        'host.blockedUsers',
                        fn (Builder $query): Builder => $query->where(
                            'blocked_user_id',
                            $user->id,
                        ),
                    );
            })
            ->latest()
            ->simplePaginate($perPage, ['*'], 'invites_page', $invitesPage);

        $applyDistance = function (MahjMatch $match) use ($validated, $hasCoordinates): void {
            if (! $hasCoordinates) {
                return;
            }

            $distance = $this->distanceMiles(
                (float) $validated['latitude'],
                (float) $validated['longitude'],
                $match->latitude,
                $match->longitude,
            );

            $match->setAttribute(
                'distance_miles',
                $distance === null ? null : round($distance, 2),
            );
        };

        $upcoming = collect($upcomingPaginator->items());
        $upcoming->each($applyDistance);

        $created = collect($createdPaginator->items());
        $created->each($applyDistance);

        $completed = collect($completedPaginator->items());
        $completed->each($applyDistance);

        $cancelled = collect($cancelledPaginator->items());
        $cancelled->each($applyDistance);

        $inviteData = collect($invitationsPaginator->items())
            ->map(function (MatchInvitation $invitation) use ($request, $applyDistance): array {
                $match = $invitation->match;
                $match->setAttribute('players_count', $match->players->count());
                $applyDistance($match);

                $avatarUrl = $invitation->inviter?->avatarUrl(
                    $request->getSchemeAndHttpHost(),
                );

                return [
                    'id' => (string) $invitation->id,
                    'status' => $invitation->status,
                    'inviter' => [
                        'id' => (string) $invitation->inviter_user_id,
                        'name' => $invitation->inviter?->name ?? 'Host',
                        'avatar_url' => $avatarUrl,
                    ],
                    'match' => (new MatchResource($match))->resolve($request),
                ];
            })
            ->values();

        return response()->json([
            'upcoming' => MatchResource::collection($upcoming)->resolve($request),
            'created_by_me' => MatchResource::collection($created)->resolve($request),
            'invites' => $inviteData,
            'completed' => MatchResource::collection($completed)->resolve($request),
            'cancelled' => MatchResource::collection($cancelled)->resolve($request),
            'meta' => [
                'per_page' => $perPage,
                'upcoming' => [
                    'page' => $upcomingPage,
                    'has_more' => $upcomingPaginator->hasMorePages(),
                ],
                'created_by_me' => [
                    'page' => $createdPage,
                    'has_more' => $createdPaginator->hasMorePages(),
                ],
                'invites' => [
                    'page' => $invitesPage,
                    'has_more' => $invitationsPaginator->hasMorePages(),
                ],
                'completed' => [
                    'page' => $completedPage,
                    'has_more' => $completedPaginator->hasMorePages(),
                ],
                'cancelled' => [
                    'page' => $cancelledPage,
                    'has_more' => $cancelledPaginator->hasMorePages(),
                ],
            ],
        ]);
    }

    public function candidates(Request $request, MahjMatch $match): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanInvite($user, $match);

        if ($match->status !== 'open') {
            throw ValidationException::withMessages([
                'match' => ['This match is not open for invitations.'],
            ]);
        }

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $search = trim((string) ($validated['q'] ?? ''));

        $excludedPlayerIds = $match->players()->pluck('users.id');
        $excludedInvitationIds = MatchInvitation::query()
            ->where('match_id', $match->id)
            ->where('status', 'pending')
            ->pluck('invitee_user_id');

        $users = User::query()
            ->where('id', '!=', $match->host_user_id)
            ->where('id', '!=', $user->id)
            ->where('is_suspended', false)
            ->whereDoesntHave(
                'blockedByUsers',
                fn (Builder $query): Builder => $query->where(
                    'blocker_user_id',
                    $match->host_user_id,
                ),
            )
            ->whereNotIn('id', $excludedPlayerIds)
            ->whereNotIn('id', $excludedInvitationIds)
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.$search.'%';

                $query->where(function (Builder $query) use ($like): void {
                    $query->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('city', 'like', $like)
                        ->orWhere('state', 'like', $like);
                });
            })
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => $users->map(fn (User $user): array => [
                'id' => (string) $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'city' => $user->city,
                'state' => $user->state,
                'avatar_url' => $user->avatarUrl(
                    $request->getSchemeAndHttpHost(),
                ),
            ])->values(),
        ]);
    }

    public function send(Request $request, MahjMatch $match): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanInvite($user, $match);

        $validated = $request->validate([
            'user_ids' => ['required', 'array', 'min:1', 'max:20'],
            'user_ids.*' => ['required', 'integer', 'distinct', 'exists:users,id'],
        ]);

        if ($match->status !== 'open') {
            throw ValidationException::withMessages([
                'match' => ['This match is not open for invitations.'],
            ]);
        }

        $userIds = collect($validated['user_ids'])->map(fn ($id): int => (int) $id)->unique();

        if ($userIds->contains($user->id)) {
            throw ValidationException::withMessages([
                'user_ids' => ['You cannot invite yourself.'],
            ]);
        }

        $existingPlayerIds = $match->players()->whereIn('users.id', $userIds)->pluck('users.id');
        if ($existingPlayerIds->isNotEmpty()) {
            throw ValidationException::withMessages([
                'user_ids' => ['One or more selected users are already in this match.'],
            ]);
        }

        $blockedInviteeExists = UserBlock::query()
            ->where('blocker_user_id', $match->host_user_id)
            ->whereIn('blocked_user_id', $userIds)
            ->exists();

        if ($blockedInviteeExists) {
            throw ValidationException::withMessages([
                'user_ids' => ['One or more selected users cannot be invited to this match.'],
            ]);
        }

        DB::transaction(function () use ($match, $user, $userIds): void {
            foreach ($userIds as $inviteeId) {
                $invitation = MatchInvitation::query()->updateOrCreate(
                    [
                        'match_id' => $match->id,
                        'invitee_user_id' => $inviteeId,
                    ],
                    [
                        'inviter_user_id' => $user->id,
                        'status' => 'pending',
                        'responded_at' => null,
                    ],
                );

                $this->notifications->matchInvitation($invitation);
            }
        });

        return response()->json([
            'message' => 'Invitations sent.',
            'count' => $userIds->count(),
        ]);
    }

    public function accept(Request $request, MatchInvitation $invitation): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureInvitee($user, $invitation);

        $match = DB::transaction(function () use ($invitation, $user): MahjMatch {
            /** @var MatchInvitation $lockedInvitation */
            $lockedInvitation = MatchInvitation::query()
                ->lockForUpdate()
                ->findOrFail($invitation->id);

            /** @var MahjMatch $match */
            $match = MahjMatch::query()
                ->lockForUpdate()
                ->findOrFail($lockedInvitation->match_id);

            if ($lockedInvitation->status === 'accepted' &&
                $match->players()->whereKey($user->id)->exists()) {
                return $match;
            }

            if ($lockedInvitation->status !== 'pending') {
                throw ValidationException::withMessages([
                    'invitation' => ['This invitation is no longer available.'],
                ]);
            }

            $blockedByHost = UserBlock::query()
                ->where('blocker_user_id', $match->host_user_id)
                ->where('blocked_user_id', $user->id)
                ->exists();

            if ($blockedByHost) {
                throw ValidationException::withMessages([
                    'invitation' => ['This invitation is no longer available.'],
                ]);
            }

            if ($match->status !== 'open') {
                throw ValidationException::withMessages([
                    'match' => ['This match is no longer open for joining.'],
                ]);
            }

            if ($match->players()->whereKey($user->id)->exists()) {
                $lockedInvitation->update([
                    'status' => 'accepted',
                    'responded_at' => now(),
                ]);

                return $match;
            }

            $count = $match->players()->count();
            if ($count >= $match->max_players) {
                throw ValidationException::withMessages([
                    'match' => ['This match is already full.'],
                ]);
            }

            $match->players()->attach($user->id, ['joined_at' => now()]);
            $this->chat->playerJoined($match, $user);
            $this->notifications->playerJoined($match, $user);
            $count++;

            $lockedInvitation->update([
                'status' => 'accepted',
                'responded_at' => now(),
            ]);

            if ($count >= $match->max_players) {
                $match->update(['status' => 'confirmed']);
                $this->chat->matchConfirmed($match);
                $this->notifications->matchConfirmed($match);
            }

            return $match;
        });

        return response()->json([
            'message' => 'Invitation accepted.',
            'match' => $this->resource($request, $match),
        ]);
    }

    public function decline(Request $request, MatchInvitation $invitation): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureInvitee($user, $invitation);

        if ($invitation->status === 'pending') {
            $invitation->update([
                'status' => 'declined',
                'responded_at' => now(),
            ]);
        }

        return response()->json([
            'message' => 'Invitation declined.',
        ]);
    }

    private function distanceMiles(
        float $originLatitude,
        float $originLongitude,
        mixed $targetLatitude,
        mixed $targetLongitude,
    ): ?float {
        if (! is_numeric($targetLatitude) || ! is_numeric($targetLongitude)) {
            return null;
        }

        $latitudeDelta = deg2rad((float) $targetLatitude - $originLatitude);
        $longitudeDelta = deg2rad((float) $targetLongitude - $originLongitude);
        $originLatitudeRadians = deg2rad($originLatitude);
        $targetLatitudeRadians = deg2rad((float) $targetLatitude);

        $a = sin($latitudeDelta / 2) ** 2
            + cos($originLatitudeRadians)
            * cos($targetLatitudeRadians)
            * sin($longitudeDelta / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(max(0, 1 - $a)));

        return self::EARTH_RADIUS_MILES * $c;
    }

    private function ensureCanInvite(User $user, MahjMatch $match): void
    {
        if ($match->host_user_id === $user->id) {
            return;
        }

        if ($match->players()->whereKey($user->id)->exists()) {
            return;
        }

        if ($match->is_public && ! $match->is_invite_only) {
            return;
        }

        abort(403, 'You cannot invite players to this match.');
    }

    private function ensureInvitee(User $user, MatchInvitation $invitation): void
    {
        if ($invitation->invitee_user_id !== $user->id) {
            abort(403, 'This invitation does not belong to you.');
        }
    }

    private function resource(Request $request, MahjMatch $match): array
    {
        $match->load(['host', 'players', 'sport'])->loadCount('players');

        return (new MatchResource($match))->resolve($request);
    }
}
