<?php

namespace App\Http\Controllers;

use App\Models\MatchChatMessage;
use App\Models\UserBlock;
use App\Models\UserDeviceToken;
use App\Models\UserNotification;
use App\Models\UserReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminCommunityController extends Controller
{
    public function chats(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $type = trim((string) $request->query('type'));

        $messages = MatchChatMessage::query()
            ->with(['match.host', 'match.sport', 'sender'])
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('body', 'like', "%{$search}%")
                    ->orWhereHas('sender', fn ($query) => $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"))
                    ->orWhereHas('match', fn ($query) => $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('venue_name', 'like', "%{$search}%")
                        ->orWhere('location_address', 'like', "%{$search}%"));
            }))
            ->when(
                in_array($type, ['message', 'system'], true),
                fn ($query) => $query->where('type', $type),
            )
            ->latest('id')
            ->limit(200)
            ->get();

        return view('admin.community.chats', [
            'messages' => $messages,
            'search' => $search,
            'type' => $type,
            'totalMessages' => MatchChatMessage::query()->count(),
            'playerMessages' => MatchChatMessage::query()->where('type', 'message')->count(),
            'systemMessages' => MatchChatMessage::query()->where('type', 'system')->count(),
            'activeChats' => MatchChatMessage::query()->distinct('match_id')->count('match_id'),
        ]);
    }

    public function notifications(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $type = trim((string) $request->query('type'));
        $status = trim((string) $request->query('status'));

        $types = UserNotification::query()
            ->select('type')
            ->distinct()
            ->orderBy('type')
            ->pluck('type');

        $notifications = UserNotification::query()
            ->with(['user', 'relatedMatch', 'relatedUser'])
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('title', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($query) => $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"))
                    ->orWhereHas('relatedUser', fn ($query) => $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"))
                    ->orWhereHas('relatedMatch', fn ($query) => $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('venue_name', 'like', "%{$search}%")
                        ->orWhere('location_address', 'like', "%{$search}%"));
            }))
            ->when(
                $type !== '' && $types->contains($type),
                fn ($query) => $query->where('type', $type),
            )
            ->when(
                $status === 'unread',
                fn ($query) => $query->whereNull('read_at'),
            )
            ->when(
                $status === 'read',
                fn ($query) => $query->whereNotNull('read_at'),
            )
            ->latest('id')
            ->limit(200)
            ->get();

        return view('admin.community.notifications', [
            'notifications' => $notifications,
            'types' => $types,
            'search' => $search,
            'type' => $type,
            'status' => $status,
            'totalNotifications' => UserNotification::query()->count(),
            'unreadNotifications' => UserNotification::query()->whereNull('read_at')->count(),
            'readNotifications' => UserNotification::query()->whereNotNull('read_at')->count(),
            'registeredDevices' => UserDeviceToken::query()->count(),
        ]);
    }

    public function reports(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $status = trim((string) $request->query('status'));

        $reports = UserReport::query()
            ->with(['reporter', 'reportedUser'])
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('reason', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('reporter', fn ($query) => $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"))
                    ->orWhereHas('reportedUser', fn ($query) => $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"));
            }))
            ->when(
                in_array($status, ['pending', 'closed'], true),
                fn ($query) => $query->where('status', $status),
            )
            ->latest('id')
            ->limit(100)
            ->get();

        return view('admin.community.reports', [
            'reports' => $reports,
            'search' => $search,
            'status' => $status,
            'totalReports' => UserReport::query()->count(),
            'pendingReports' => UserReport::query()->where('status', 'pending')->count(),
            'closedReports' => UserReport::query()->where('status', 'closed')->count(),
        ]);
    }

    public function updateReport(Request $request, UserReport $report): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['pending', 'closed'])],
        ]);

        $status = $validated['status'];
        $report->update([
            'status' => $status,
            'reviewed_at' => $status === 'closed' ? now() : null,
        ]);

        return back()->with(
            'status',
            $status === 'closed' ? 'Report closed.' : 'Report reopened.',
        );
    }

    public function blocks(Request $request): View
    {
        $search = trim((string) $request->query('search'));

        $blocks = UserBlock::query()
            ->with(['blocker', 'blockedUser'])
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('reason', 'like', "%{$search}%")
                    ->orWhereHas('blocker', fn ($query) => $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"))
                    ->orWhereHas('blockedUser', fn ($query) => $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"));
            }))
            ->latest('id')
            ->limit(100)
            ->get();

        return view('admin.community.blocks', [
            'blocks' => $blocks,
            'search' => $search,
            'totalBlocks' => UserBlock::query()->count(),
        ]);
    }
}
