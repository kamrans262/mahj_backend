<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MatchResource;
use App\Models\MahjMatch;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MatchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $matches = MahjMatch::query()
            ->with(['host', 'players', 'sport'])
            ->withCount('players')
            ->where(function ($query) use ($user): void {
                $query->where(function ($query): void {
                    $query->where('is_public', true)
                        ->where('is_invite_only', false);
                })
                    ->orWhere('host_user_id', $user->id)
                    ->orWhereHas('players', fn ($query) => $query->whereKey($user->id));
            })
            ->whereIn('status', ['open', 'confirmed'])
            ->where('starts_at', '>=', now()->subHours(3))
            ->orderBy('starts_at')
            ->limit(100)
            ->get();

        return response()->json([
            'data' => MatchResource::collection($matches)->resolve($request),
        ]);
    }

    public function show(Request $request, MahjMatch $match): JsonResponse
    {
        $this->ensureVisible($request->user(), $match);

        return response()->json([
            'match' => $this->resource($request, $match),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sport_id' => ['nullable', 'integer', 'exists:sports,id', 'required_without_all:custom_sport_name,name'],
            'custom_sport_name' => ['nullable', 'string', 'max:100', 'required_without_all:sport_id,name'],
            'name' => ['nullable', 'string', 'max:120', 'required_without_all:sport_id,custom_sport_name'],
            'location_address' => ['required', 'string', 'max:255'],
            'venue_name' => ['nullable', 'string', 'max:160'],
            'starts_at' => ['required', 'date', 'after:now'],
            'is_public' => ['required', 'boolean'],
            'is_invite_only' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $sport = isset($validated['sport_id'])
            ? Sport::query()->where('is_active', true)->find($validated['sport_id'])
            : null;

        if (isset($validated['sport_id']) && $sport === null) {
            throw ValidationException::withMessages([
                'sport_id' => ['The selected sport is not available.'],
            ]);
        }

        $customSportName = filled($validated['custom_sport_name'] ?? null)
            ? trim((string) $validated['custom_sport_name'])
            : null;
        $legacyName = filled($validated['name'] ?? null)
            ? trim((string) $validated['name'])
            : null;
        $displayName = $sport?->name ?? $customSportName ?? $legacyName;

        $match = DB::transaction(function () use ($validated, $user, $sport, $customSportName, $displayName): MahjMatch {
            $inviteOnly = (bool) $validated['is_invite_only'];

            $match = MahjMatch::query()->create([
                'host_user_id' => $user->id,
                'name' => $displayName,
                'sport_id' => $sport?->id,
                'custom_sport_name' => $sport === null ? $customSportName : null,
                'location_address' => trim($validated['location_address']),
                'venue_name' => filled($validated['venue_name'] ?? null)
                    ? trim((string) $validated['venue_name'])
                    : null,
                'starts_at' => $validated['starts_at'],
                'is_public' => $inviteOnly ? false : (bool) $validated['is_public'],
                'is_invite_only' => $inviteOnly,
                'status' => 'open',
                'max_players' => 4,
                'notes' => filled($validated['notes'] ?? null)
                    ? trim((string) $validated['notes'])
                    : null,
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
            ]);

            $match->players()->attach($user->id, ['joined_at' => now()]);

            return $match;
        });

        return response()->json([
            'message' => 'Match created.',
            'match' => $this->resource($request, $match),
        ], 201);
    }

    public function join(Request $request, MahjMatch $match): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $match = DB::transaction(function () use ($match, $user): MahjMatch {
            /** @var MahjMatch $locked */
            $locked = MahjMatch::query()->lockForUpdate()->findOrFail($match->id);

            if ($locked->host_user_id === $user->id) {
                throw ValidationException::withMessages([
                    'match' => ['You are already the host of this match.'],
                ]);
            }

            if ($locked->players()->whereKey($user->id)->exists()) {
                return $locked;
            }

            if (! $locked->is_public || $locked->is_invite_only) {
                throw ValidationException::withMessages([
                    'match' => ['This match requires an invitation.'],
                ]);
            }

            if ($locked->status !== 'open') {
                throw ValidationException::withMessages([
                    'match' => ['This match is not open for joining.'],
                ]);
            }

            $count = $locked->players()->count();
            if ($count >= $locked->max_players) {
                throw ValidationException::withMessages([
                    'match' => ['This match is already full.'],
                ]);
            }

            $locked->players()->attach($user->id, ['joined_at' => now()]);
            $count++;

            if ($count >= $locked->max_players) {
                $locked->update(['status' => 'confirmed']);
            }

            return $locked;
        });

        return response()->json([
            'message' => 'You joined the match.',
            'match' => $this->resource($request, $match),
        ]);
    }

    public function leave(Request $request, MahjMatch $match): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $match = DB::transaction(function () use ($match, $user): MahjMatch {
            /** @var MahjMatch $locked */
            $locked = MahjMatch::query()->lockForUpdate()->findOrFail($match->id);

            if ($locked->host_user_id === $user->id) {
                throw ValidationException::withMessages([
                    'match' => ['The host cannot leave their own match. Cancel it instead.'],
                ]);
            }

            if (! $locked->players()->whereKey($user->id)->exists()) {
                throw ValidationException::withMessages([
                    'match' => ['You are not part of this match.'],
                ]);
            }

            if (in_array($locked->status, ['cancelled', 'completed'], true)) {
                throw ValidationException::withMessages([
                    'match' => ['This match can no longer be left.'],
                ]);
            }

            $locked->players()->detach($user->id);
            $count = $locked->players()->count();

            if ($locked->status === 'confirmed' && $count < $locked->max_players) {
                $locked->update(['status' => 'open']);
            }

            return $locked;
        });

        return response()->json([
            'message' => 'You left the match.',
            'match' => $this->resource($request, $match),
        ]);
    }

    public function cancel(Request $request, MahjMatch $match): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($match->host_user_id !== $user->id) {
            abort(403, 'Only the host can cancel this match.');
        }

        if ($match->status === 'completed') {
            throw ValidationException::withMessages([
                'match' => ['A completed match cannot be cancelled.'],
            ]);
        }

        if ($match->status !== 'cancelled') {
            $match->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);
        }

        return response()->json([
            'message' => 'Match cancelled.',
            'match' => $this->resource($request, $match),
        ]);
    }

    private function ensureVisible(User $user, MahjMatch $match): void
    {
        if ($match->is_public && ! $match->is_invite_only) {
            return;
        }

        if ($match->host_user_id === $user->id) {
            return;
        }

        if ($match->players()->whereKey($user->id)->exists()) {
            return;
        }

        abort(403, 'This match is private.');
    }

    private function resource(Request $request, MahjMatch $match): array
    {
        $match->load(['host', 'players', 'sport'])->loadCount('players');

        return (new MatchResource($match))->resolve($request);
    }
}
