<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MahjMatch;
use App\Models\MatchChatMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MatchChatController extends Controller
{
    public function index(Request $request, MahjMatch $match): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureParticipant($user, $match);

        $validated = $request->validate([
            'before_id' => ['nullable', 'integer', 'min:1'],
            'after_id' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        if (isset($validated['before_id'], $validated['after_id'])) {
            throw ValidationException::withMessages([
                'before_id' => ['Use either before_id or after_id, not both.'],
            ]);
        }

        $limit = (int) ($validated['limit'] ?? 30);
        $query = MatchChatMessage::query()
            ->with('sender')
            ->where('match_id', $match->id);

        if (isset($validated['after_id'])) {
            $messages = $query
                ->where('id', '>', (int) $validated['after_id'])
                ->orderBy('id')
                ->limit($limit)
                ->get();
        } else {
            if (isset($validated['before_id'])) {
                $query->where('id', '<', (int) $validated['before_id']);
            }

            $messages = $query
                ->orderByDesc('id')
                ->limit($limit)
                ->get()
                ->reverse()
                ->values();
        }

        $firstId = $messages->first()?->id;
        $hasMoreOlder = $firstId !== null
            && MatchChatMessage::query()
                ->where('match_id', $match->id)
                ->where('id', '<', $firstId)
                ->exists();

        $match->load(['players' => fn ($query) => $query->orderBy('match_players.joined_at')]);

        return response()->json([
            'match' => [
                'id' => (string) $match->id,
                'starts_at' => $match->starts_at?->toISOString(),
                'status' => $match->status,
            ],
            'participants' => $match->players
                ->map(fn (User $player): array => $this->participantData($request, $player))
                ->values(),
            'messages' => $messages
                ->map(fn (MatchChatMessage $message): array => $this->messageData($request, $message))
                ->values(),
            'meta' => [
                'has_more_older' => $hasMoreOlder,
                'can_send' => $this->canSend($match),
            ],
        ]);
    }

    public function send(Request $request, MahjMatch $match): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureParticipant($user, $match);

        if (! $this->canSend($match)) {
            throw ValidationException::withMessages([
                'chat' => ['Chat is no longer available for this match.'],
            ]);
        }

        $validated = $request->validate([
            'text' => ['required', 'string', 'max:2000'],
        ]);

        $text = trim((string) $validated['text']);
        if ($text === '') {
            throw ValidationException::withMessages([
                'text' => ['Enter a message before sending.'],
            ]);
        }

        $message = MatchChatMessage::query()->create([
            'match_id' => $match->id,
            'sender_user_id' => $user->id,
            'type' => 'message',
            'body' => $text,
        ]);
        $message->setRelation('sender', $user);

        return response()->json([
            'message' => $this->messageData($request, $message),
        ], 201);
    }

    private function ensureParticipant(User $user, MahjMatch $match): void
    {
        if ($match->host_user_id === $user->id) {
            return;
        }

        if ($match->players()->whereKey($user->id)->exists()) {
            return;
        }

        abort(403, 'Only match participants can access this chat.');
    }

    private function canSend(MahjMatch $match): bool
    {
        return ! in_array($match->status, ['cancelled', 'completed'], true);
    }

    private function participantData(Request $request, User $user): array
    {
        return [
            'id' => (string) $user->id,
            'name' => $user->name,
            'avatar_url' => $this->avatarUrl($request, $user),
            'is_online' => false,
        ];
    }

    private function messageData(Request $request, MatchChatMessage $message): array
    {
        $sender = $message->sender;

        return [
            'id' => (string) $message->id,
            'match_id' => (string) $message->match_id,
            'type' => $message->type,
            'event_key' => $message->event_key,
            'text' => $message->body,
            'created_at' => $message->created_at?->toISOString(),
            'sender' => $sender === null ? null : [
                'id' => (string) $sender->id,
                'name' => $sender->name,
                'avatar_url' => $this->avatarUrl($request, $sender),
            ],
        ];
    }

    private function avatarUrl(Request $request, User $user): ?string
    {
        if (! $user->avatar_path) {
            return null;
        }

        return rtrim($request->getSchemeAndHttpHost(), '/')
            .'/storage/'
            .ltrim($user->avatar_path, '/');
    }
}
