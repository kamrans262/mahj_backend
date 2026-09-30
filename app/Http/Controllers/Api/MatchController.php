<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MatchResource;
use App\Models\MahjMatch;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MatchController extends Controller
{
    private const MAX_DISCOVERY_ROWS = 250;
    private const MAX_RESPONSE_ROWS = 100;
    private const EARTH_RADIUS_MILES = 3958.7613;

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'radius_miles' => ['nullable', 'numeric', 'min:1', 'max:100'],
            'date_filter' => ['nullable', Rule::in(['any', 'today', 'tomorrow', 'weekend', 'next_3_days'])],
            'open_spots_only' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(['distance', 'date'])],
            'q' => ['nullable', 'string', 'max:120'],
            'discover_only' => ['nullable', 'boolean'],
            'timezone_offset_minutes' => ['nullable', 'integer', 'between:-840,840'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $query = MahjMatch::query()
            ->with(['host', 'players', 'sport'])
            ->withCount('players')
            ->whereIn('status', ['open', 'confirmed'])
            ->where('starts_at', '>=', now()->subHours(3));

        if ($request->boolean('discover_only')) {
            $query
                ->where('is_public', true)
                ->where('is_invite_only', false)
                ->where('host_user_id', '!=', $user->id)
                ->whereDoesntHave('players', fn (Builder $query): Builder => $query->whereKey($user->id));
        } else {
            $query->where(function (Builder $query) use ($user): void {
                $query->where(function (Builder $query): void {
                    $query->where('is_public', true)
                        ->where('is_invite_only', false);
                })
                    ->orWhere('host_user_id', $user->id)
                    ->orWhereHas('players', fn (Builder $query): Builder => $query->whereKey($user->id));
            });
        }

        $search = trim((string) ($validated['q'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $like = '%'.$search.'%';
                $query->where('name', 'like', $like)
                    ->orWhere('custom_sport_name', 'like', $like)
                    ->orWhere('location_address', 'like', $like)
                    ->orWhere('venue_name', 'like', $like)
                    ->orWhereHas('sport', fn (Builder $query): Builder => $query->where('name', 'like', $like));
            });
        }

        $this->applyDateFilter(
            $query,
            (string) ($validated['date_filter'] ?? 'any'),
            (int) ($validated['timezone_offset_minutes'] ?? 0),
        );

        /** @var Collection<int, MahjMatch> $matches */
        $matches = $query
            ->orderBy('starts_at')
            ->limit(self::MAX_DISCOVERY_ROWS)
            ->get();

        if ($request->boolean('open_spots_only')) {
            $matches = $matches
                ->filter(fn (MahjMatch $match): bool =>
                    $match->status === 'open' && (int) $match->players_count < $match->max_players)
                ->values();
        }

        $hasCoordinates = isset($validated['latitude'], $validated['longitude']);
        if ($hasCoordinates) {
            $originLatitude = (float) $validated['latitude'];
            $originLongitude = (float) $validated['longitude'];

            $matches->each(function (MahjMatch $match) use ($originLatitude, $originLongitude): void {
                $distance = $this->distanceMiles(
                    $originLatitude,
                    $originLongitude,
                    $match->latitude,
                    $match->longitude,
                );

                $match->setAttribute('distance_miles', $distance === null ? null : round($distance, 2));
            });

            if (isset($validated['radius_miles'])) {
                $radiusMiles = (float) $validated['radius_miles'];
                $matches = $matches
                    ->filter(function (MahjMatch $match) use ($radiusMiles): bool {
                        $distance = $match->getAttribute('distance_miles');

                        return is_numeric($distance) && (float) $distance <= $radiusMiles;
                    })
                    ->values();
            }
        }

        $sort = (string) ($validated['sort'] ?? 'date');
        if ($sort === 'distance' && $hasCoordinates) {
            $matches = $matches->sort(function (MahjMatch $left, MahjMatch $right): int {
                $leftDistance = $left->getAttribute('distance_miles');
                $rightDistance = $right->getAttribute('distance_miles');

                $leftValue = is_numeric($leftDistance) ? (float) $leftDistance : INF;
                $rightValue = is_numeric($rightDistance) ? (float) $rightDistance : INF;
                $distanceOrder = $leftValue <=> $rightValue;

                return $distanceOrder !== 0
                    ? $distanceOrder
                    : $left->starts_at <=> $right->starts_at;
            })->values();
        } else {
            $matches = $matches->sortBy('starts_at')->values();
        }

        $matches = $matches->take(self::MAX_RESPONSE_ROWS)->values();

        return response()->json([
            'data' => MatchResource::collection($matches)->resolve($request),
            'meta' => [
                'count' => $matches->count(),
                'filters' => [
                    'radius_miles' => isset($validated['radius_miles']) ? (float) $validated['radius_miles'] : null,
                    'date_filter' => (string) ($validated['date_filter'] ?? 'any'),
                    'open_spots_only' => $request->boolean('open_spots_only'),
                    'sort' => $sort,
                ],
            ],
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
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
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

    private function applyDateFilter(Builder $query, string $dateFilter, int $offsetMinutes): void
    {
        if ($dateFilter === 'any') {
            return;
        }

        $localNow = now('UTC')->addMinutes($offsetMinutes);

        if ($dateFilter === 'today') {
            $start = $localNow->copy()->startOfDay();
            $end = $localNow->copy()->endOfDay();
        } elseif ($dateFilter === 'tomorrow') {
            $start = $localNow->copy()->addDay()->startOfDay();
            $end = $localNow->copy()->addDay()->endOfDay();
        } elseif ($dateFilter === 'next_3_days') {
            $start = $localNow->copy()->startOfDay();
            $end = $localNow->copy()->addDays(2)->endOfDay();
        } else {
            if ($localNow->isSaturday()) {
                $start = $localNow->copy()->startOfDay();
            } elseif ($localNow->isSunday()) {
                $start = $localNow->copy()->subDay()->startOfDay();
            } else {
                $start = $localNow->copy()->next('Saturday')->startOfDay();
            }

            $end = $start->copy()->addDay()->endOfDay();
        }

        $query->whereBetween('starts_at', [
            $start->copy()->subMinutes($offsetMinutes),
            $end->copy()->subMinutes($offsetMinutes),
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
