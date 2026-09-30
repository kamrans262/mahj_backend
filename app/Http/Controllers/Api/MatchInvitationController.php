<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MatchResource;
use App\Models\MahjMatch;
use App\Models\MatchInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MatchInvitationController extends Controller
{
    public function myMatches(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $upcoming = MahjMatch::query()
            ->with(['host', 'players', 'sport'])
            ->withCount('players')
            ->where('host_user_id', '!=', $user->id)
            ->whereHas('players', fn (Builder $query): Builder => $query->whereKey($user->id))
            ->where('starts_at', '>=', now()->subHours(3))
            ->where('status', '!=', 'completed')
            ->orderBy('starts_at')
            ->limit(100)
            ->get();

        $created = MahjMatch::query()
            ->with(['host', 'players', 'sport'])
            ->withCount('players')
            ->where('host_user_id', $user->id)
            ->orderByDesc('starts_at')
            ->limit(100)
            ->get();

        $invitations = MatchInvitation::query()
            ->with([
                'inviter',
                'match.host',
                'match.players',
                'match.sport',
            ])
            ->where('invitee_user_id', $user->id)
            ->where('status', 'pending')
            ->whereHas('match', function (Builder $query): void {
                $query->whereNotIn('status', ['cancelled', 'completed'])
                    ->where('starts_at', '>=', now()->subHours(3));
            })
            ->latest()
            ->limit(100)
            ->get();

        $inviteData = $invitations->map(function (MatchInvitation $invitation) use ($request): array {
            $match = $invitation->match;
            $match->setAttribute('players_count', $match->players->count());

            return [
                'id' => (string) $invitation->id,
                'status' => $invitation->status,
                'inviter' => [
                    'id' => (string) $invitation->inviter_user_id,
                    'name' => $invitation->inviter?->name ?? 'Host',
                ],
                'match' => (new MatchResource($match))->resolve($request),
            ];
        })->values();

        return response()->json([
            'upcoming' => MatchResource::collection($upcoming)->resolve($request),
            'created_by_me' => MatchResource::collection($created)->resolve($request),
            'invites' => $inviteData,
        ]);
    }

    public function candidates(Request $request, MahjMatch $match): JsonResponse
    {
        $this->ensureHost($request->user(), $match);

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
            ->where('is_suspended', false)
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
            ])->values(),
        ]);
    }

    public function send(Request $request, MahjMatch $match): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureHost($user, $match);

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

        DB::transaction(function () use ($match, $user, $userIds): void {
            foreach ($userIds as $inviteeId) {
                MatchInvitation::query()->updateOrCreate(
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
            $count++;

            $lockedInvitation->update([
                'status' => 'accepted',
                'responded_at' => now(),
            ]);

            if ($count >= $match->max_players) {
                $match->update(['status' => 'confirmed']);
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

    private function ensureHost(User $user, MahjMatch $match): void
    {
        if ($match->host_user_id !== $user->id) {
            abort(403, 'Only the host can manage invitations for this match.');
        }
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
