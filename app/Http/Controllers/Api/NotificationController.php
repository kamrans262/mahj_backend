<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 20);

        $paginator = UserNotification::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->simplePaginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => collect($paginator->items())
                ->map(fn (UserNotification $notification): array => $this->notificationData($notification))
                ->values(),
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'has_more' => $paginator->hasMorePages(),
                'unread_count' => UserNotification::query()
                    ->where('user_id', $user->id)
                    ->whereNull('read_at')
                    ->count(),
            ],
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'unread_count' => UserNotification::query()
                ->where('user_id', $user->id)
                ->whereNull('read_at')
                ->count(),
        ]);
    }

    public function markRead(Request $request, UserNotification $notification): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($notification->user_id !== $user->id) {
            abort(404);
        }

        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }

        return response()->json([
            'notification' => $this->notificationData($notification->refresh()),
            'unread_count' => UserNotification::query()
                ->where('user_id', $user->id)
                ->whereNull('read_at')
                ->count(),
        ]);
    }

    public function settings(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'settings' => $this->settingsData($this->preferences($user)),
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'new_games_nearby' => ['required', 'boolean'],
            'game_invitations' => ['required', 'boolean'],
            'players_joining_my_game' => ['required', 'boolean'],
            'game_confirmations' => ['required', 'boolean'],
            'game_reminders' => ['required', 'boolean'],
            'schedule_changes' => ['required', 'boolean'],
            'new_messages' => ['required', 'boolean'],
            'subscription_updates' => ['required', 'boolean'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $preferences = $this->preferences($user);
        $preferences->update($validated);

        return response()->json([
            'message' => 'Notification settings saved.',
            'settings' => $this->settingsData($preferences->refresh()),
        ]);
    }

    private function preferences(User $user): NotificationPreference
    {
        return NotificationPreference::query()->firstOrCreate([
            'user_id' => $user->id,
        ]);
    }

    private function notificationData(UserNotification $notification): array
    {
        return [
            'id' => (string) $notification->id,
            'type' => $notification->type,
            'title' => $notification->title,
            'message' => $notification->message,
            'created_at' => $notification->created_at?->toISOString(),
            'is_read' => $notification->read_at !== null,
            'related_match_id' => $notification->related_match_id === null
                ? null
                : (string) $notification->related_match_id,
            'related_user_id' => $notification->related_user_id === null
                ? null
                : (string) $notification->related_user_id,
            'data' => $notification->data ?? [],
        ];
    }

    private function settingsData(NotificationPreference $preferences): array
    {
        return [
            'new_games_nearby' => $preferences->new_games_nearby,
            'game_invitations' => $preferences->game_invitations,
            'players_joining_my_game' => $preferences->players_joining_my_game,
            'game_confirmations' => $preferences->game_confirmations,
            'game_reminders' => $preferences->game_reminders,
            'schedule_changes' => $preferences->schedule_changes,
            'new_messages' => $preferences->new_messages,
            'subscription_updates' => $preferences->subscription_updates,
        ];
    }
}
