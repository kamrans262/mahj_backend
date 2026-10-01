<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MatchResource;
use App\Models\MahjMatch;
use App\Models\MatchScore;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MatchCompletionController extends Controller
{
    public function show(Request $request, MahjMatch $match): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->ensureParticipant($user, $match);
        $this->ensureCompleted($match);

        return response()->json($this->payload($request, $match));
    }

    public function complete(Request $request, MahjMatch $match): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($match->host_user_id !== $user->id) {
            abort(403, 'Only the host can complete this match.');
        }

        if ($match->status === 'cancelled') {
            throw ValidationException::withMessages([
                'match' => ['A cancelled match cannot be completed.'],
            ]);
        }

        if ($match->status !== 'completed' && $match->starts_at?->isFuture()) {
            throw ValidationException::withMessages([
                'match' => ['A match cannot be completed before it starts.'],
            ]);
        }

        if ($match->status !== 'completed') {
            $match->update([
                'status' => 'completed',
                'completed_at' => now(),
                'cancelled_at' => null,
            ]);
        }

        return response()->json([
            'message' => 'Match completed.',
            ...$this->payload($request, $match),
        ]);
    }

    public function submitScores(Request $request, MahjMatch $match): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->ensureParticipant($user, $match);
        $this->ensureCompleted($match);

        $validated = $request->validate([
            'scores' => ['required', 'array', 'min:1'],
            'scores.*.player_id' => ['required', 'integer', 'distinct', 'exists:users,id'],
            'scores.*.score' => ['required', 'integer'],
        ]);

        DB::transaction(function () use ($match, $user, $validated): void {
            /** @var MahjMatch $locked */
            $locked = MahjMatch::query()
                ->lockForUpdate()
                ->findOrFail($match->id);

            $this->ensureCompleted($locked);

            if ($locked->scores()->exists()) {
                return;
            }

            $playerIds = $locked->players()
                ->orderBy('users.id')
                ->pluck('users.id')
                ->map(fn ($id): int => (int) $id)
                ->values();

            $submitted = collect($validated['scores'])
                ->map(fn (array $score): array => [
                    'player_id' => (int) $score['player_id'],
                    'score' => (int) $score['score'],
                ]);

            $submittedIds = $submitted
                ->pluck('player_id')
                ->sort()
                ->values();

            if ($submittedIds->all() !== $playerIds->all()) {
                throw ValidationException::withMessages([
                    'scores' => ['Scores must be provided for every match player exactly once.'],
                ]);
            }

            foreach ($submitted as $score) {
                MatchScore::query()->create([
                    'match_id' => $locked->id,
                    'player_user_id' => $score['player_id'],
                    'score' => $score['score'],
                    'submitted_by_user_id' => $user->id,
                ]);
            }
        });

        $fresh = MahjMatch::query()->findOrFail($match->id);

        return response()->json([
            'message' => 'Scores submitted successfully.',
            ...$this->payload($request, $fresh),
        ]);
    }

    private function ensureParticipant(User $user, MahjMatch $match): void
    {
        if ($match->host_user_id === $user->id) {
            return;
        }

        if ($match->players()->whereKey($user->id)->exists()) {
            return;
        }

        abort(403, 'Only match players can view or submit scores.');
    }

    private function ensureCompleted(MahjMatch $match): void
    {
        if ($match->status !== 'completed') {
            throw ValidationException::withMessages([
                'match' => ['Scores are available only after the match is completed.'],
            ]);
        }
    }

    private function payload(Request $request, MahjMatch $match): array
    {
        $match->load(['host', 'players', 'sport', 'scores'])->loadCount('players');

        $scores = $match->scores->keyBy('player_user_id');
        $scoresSubmitted = $match->scores->isNotEmpty();

        /** @var User|null $user */
        $user = $request->user();
        $isParticipant = $user !== null && (
            $match->host_user_id === $user->id ||
            $match->players->contains('id', $user->id)
        );

        return [
            'match' => (new MatchResource($match))->resolve($request),
            'players' => $match->players
                ->map(fn (User $player): array => [
                    'id' => (string) $player->id,
                    'name' => $player->name,
                    'avatar_url' => $player->avatarUrl(
                        $request->getSchemeAndHttpHost(),
                    ),
                    'score' => $scores->get($player->id)?->score,
                ])
                ->values(),
            'scores_submitted' => $scoresSubmitted,
            'can_submit_scores' => $isParticipant && ! $scoresSubmitted,
        ];
    }
}
