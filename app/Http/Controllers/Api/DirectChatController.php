<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DirectConversation;
use App\Models\DirectMessage;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DirectChatController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $request->user();

        $conversations = DirectConversation::query()
            ->with(['userOne', 'userTwo', 'latestMessage.sender'])
            ->where(function ($query) use ($currentUser): void {
                $query->where('user_one_id', $currentUser->id)
                    ->orWhere('user_two_id', $currentUser->id);
            })
            ->orderByRaw('COALESCE(last_message_at, created_at) DESC')
            ->limit(100)
            ->get();

        return response()->json([
            'data' => $conversations
                ->filter(fn (DirectConversation $conversation): bool =>
                    $this->otherUser($conversation, $currentUser) !== null)
                ->map(fn (DirectConversation $conversation): array =>
                    $this->conversationData($request, $conversation, $currentUser))
                ->values(),
        ]);
    }

    public function start(Request $request, User $user): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $request->user();

        if ($currentUser->id === $user->id) {
            throw ValidationException::withMessages([
                'user' => ['You cannot start a chat with yourself.'],
            ]);
        }

        if ($user->is_suspended) {
            abort(404, 'Player not found.');
        }

        $this->ensureNotBlocked($currentUser, $user);

        $firstId = min($currentUser->id, $user->id);
        $secondId = max($currentUser->id, $user->id);

        $conversation = DirectConversation::query()->firstOrCreate([
            'user_one_id' => $firstId,
            'user_two_id' => $secondId,
        ]);

        $conversation->load(['userOne', 'userTwo', 'latestMessage.sender']);

        return response()->json([
            'conversation' => $this->conversationData(
                $request,
                $conversation,
                $currentUser,
            ),
        ]);
    }

    public function show(Request $request, DirectConversation $conversation): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $request->user();
        $other = $this->ensureParticipant($currentUser, $conversation);
        $this->ensureNotBlocked($currentUser, $other);

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
        $query = DirectMessage::query()
            ->with('sender')
            ->where('direct_conversation_id', $conversation->id);

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
            && DirectMessage::query()
                ->where('direct_conversation_id', $conversation->id)
                ->where('id', '<', $firstId)
                ->exists();

        return response()->json([
            'conversation' => [
                'id' => (string) $conversation->id,
                'other_user' => $this->participantData($request, $other),
            ],
            'messages' => $messages
                ->map(fn (DirectMessage $message): array =>
                    $this->messageData($request, $message))
                ->values(),
            'meta' => [
                'has_more_older' => $hasMoreOlder,
            ],
        ]);
    }

    public function send(Request $request, DirectConversation $conversation): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $request->user();
        $other = $this->ensureParticipant($currentUser, $conversation);
        $this->ensureNotBlocked($currentUser, $other);

        $validated = $request->validate([
            'text' => ['required', 'string', 'max:2000'],
        ]);

        $text = trim((string) $validated['text']);
        if ($text === '') {
            throw ValidationException::withMessages([
                'text' => ['Enter a message before sending.'],
            ]);
        }

        $message = DirectMessage::query()->create([
            'direct_conversation_id' => $conversation->id,
            'sender_user_id' => $currentUser->id,
            'body' => $text,
        ]);
        $message->setRelation('sender', $currentUser);

        $conversation->update(['last_message_at' => now()]);

        return response()->json([
            'message' => $this->messageData($request, $message),
        ], 201);
    }

    private function ensureParticipant(
        User $currentUser,
        DirectConversation $conversation,
    ): User {
        if ($conversation->user_one_id !== $currentUser->id
            && $conversation->user_two_id !== $currentUser->id) {
            abort(403, 'This chat is not available.');
        }

        $conversation->loadMissing(['userOne', 'userTwo']);
        $other = $this->otherUser($conversation, $currentUser);

        if ($other === null || $other->is_suspended) {
            abort(404, 'Player not found.');
        }

        return $other;
    }

    private function ensureNotBlocked(User $currentUser, User $other): void
    {
        $blocked = UserBlock::query()
            ->where(function ($query) use ($currentUser, $other): void {
                $query->where('blocker_user_id', $currentUser->id)
                    ->where('blocked_user_id', $other->id);
            })
            ->orWhere(function ($query) use ($currentUser, $other): void {
                $query->where('blocker_user_id', $other->id)
                    ->where('blocked_user_id', $currentUser->id);
            })
            ->exists();

        if ($blocked) {
            abort(403, 'This chat is not available.');
        }
    }

    private function otherUser(
        DirectConversation $conversation,
        User $currentUser,
    ): ?User {
        if ($conversation->user_one_id === $currentUser->id) {
            return $conversation->userTwo;
        }

        if ($conversation->user_two_id === $currentUser->id) {
            return $conversation->userOne;
        }

        return null;
    }

    private function conversationData(
        Request $request,
        DirectConversation $conversation,
        User $currentUser,
    ): array {
        $other = $this->otherUser($conversation, $currentUser);
        $latest = $conversation->latestMessage;

        return [
            'id' => (string) $conversation->id,
            'other_user' => $other === null
                ? null
                : $this->participantData($request, $other),
            'last_message' => $latest === null
                ? null
                : $this->messageData($request, $latest),
            'updated_at' => ($conversation->last_message_at ?? $conversation->created_at)
                ?->toISOString(),
        ];
    }

    private function participantData(Request $request, User $user): array
    {
        return [
            'id' => (string) $user->id,
            'name' => $user->name,
            'avatar_url' => $user->avatarUrl($request->getSchemeAndHttpHost()),
            'is_online' => false,
        ];
    }

    private function messageData(Request $request, DirectMessage $message): array
    {
        $sender = $message->sender;

        return [
            'id' => (string) $message->id,
            'conversation_id' => (string) $message->direct_conversation_id,
            'type' => 'message',
            'text' => $message->body,
            'created_at' => $message->created_at?->toISOString(),
            'sender' => $sender === null ? null : [
                'id' => (string) $sender->id,
                'name' => $sender->name,
                'avatar_url' => $sender->avatarUrl(
                    $request->getSchemeAndHttpHost(),
                ),
            ],
        ];
    }
}
