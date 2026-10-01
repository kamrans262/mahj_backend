<?php

namespace App\Http\Controllers;

use App\Models\MatchChatMessage;
use App\Models\UserBlock;
use App\Models\UserReport;
use Illuminate\Http\Request;
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

    public function reports(Request $request): View
    {
        $search = trim((string) $request->query('search'));

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
            ->latest('id')
            ->limit(100)
            ->get();

        return view('admin.community.reports', [
            'reports' => $reports,
            'search' => $search,
            'totalReports' => UserReport::query()->count(),
            'pendingReports' => UserReport::query()->where('status', 'pending')->count(),
            'reviewedReports' => UserReport::query()->whereNotNull('reviewed_at')->count(),
        ]);
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
