<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserBlock;
use App\Models\UserReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserModerationController extends Controller
{
    private const REPORT_REASONS = [
        'inappropriate_behavior',
        'harassment_or_abusive_language',
        'spam_or_unwanted_messages',
        'offensive_profile_or_content',
        'fake_or_misleading_profile',
        'cheating_or_unfair_behavior',
        'safety_concern',
        'other',
    ];

    private const BLOCK_REASONS = [
        'harassment_or_abusive_behavior',
        'spam_or_unwanted_messages',
        'inappropriate_behavior',
        'do_not_want_to_play',
        'repeated_no_shows',
        'safety_concern',
        'other',
    ];

    public function index(Request $request): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $request->user();

        $blocks = UserBlock::query()
            ->where('blocker_user_id', $currentUser->id)
            ->with([
                'blockedUser' => fn ($query) => $query->withCount('joinedMatches'),
            ])
            ->latest('id')
            ->limit(100)
            ->get();

        $reports = UserReport::query()
            ->where('reporter_user_id', $currentUser->id)
            ->with([
                'reportedUser' => fn ($query) => $query->withCount('joinedMatches'),
            ])
            ->latest('id')
            ->limit(100)
            ->get();

        return response()->json([
            'blocked_users' => $blocks
                ->filter(fn (UserBlock $block): bool => $block->blockedUser !== null)
                ->map(fn (UserBlock $block): array => $this->userData(
                    $request,
                    $block->blockedUser,
                ))
                ->values(),
            'report_history' => $reports
                ->filter(fn (UserReport $report): bool => $report->reportedUser !== null)
                ->map(fn (UserReport $report): array => [
                    'id' => (string) $report->id,
                    'status' => $report->status === 'closed' ? 'closed' : 'pending',
                    'reason' => $report->reason,
                    'created_at' => $report->created_at?->toISOString(),
                    'reported_user' => $this->userData(
                        $request,
                        $report->reportedUser,
                    ),
                ])
                ->values(),
        ]);
    }

    public function report(Request $request, User $user): JsonResponse
    {
        /** @var User $reporter */
        $reporter = $request->user();
        $this->ensureDifferentUsers($reporter, $user);

        $validated = $request->validate([
            'reason_id' => ['required', 'string', Rule::in(self::REPORT_REASONS)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $report = UserReport::query()->create([
            'reporter_user_id' => $reporter->id,
            'reported_user_id' => $user->id,
            'reason' => $validated['reason_id'],
            'notes' => filled($validated['notes'] ?? null)
                ? trim((string) $validated['notes'])
                : null,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Report submitted.',
            'report_id' => (string) $report->id,
            'status' => 'pending',
        ], 201);
    }

    public function block(Request $request, User $user): JsonResponse
    {
        /** @var User $blocker */
        $blocker = $request->user();
        $this->ensureDifferentUsers($blocker, $user);

        $validated = $request->validate([
            'reason_id' => ['required', 'string', Rule::in(self::BLOCK_REASONS)],
        ]);

        UserBlock::query()->updateOrCreate(
            [
                'blocker_user_id' => $blocker->id,
                'blocked_user_id' => $user->id,
            ],
            [
                'reason' => $validated['reason_id'],
            ],
        );

        return response()->json([
            'message' => 'Player blocked.',
            'blocked' => true,
        ]);
    }

    public function unblock(Request $request, User $user): JsonResponse
    {
        /** @var User $blocker */
        $blocker = $request->user();
        $this->ensureDifferentUsers($blocker, $user);

        UserBlock::query()
            ->where('blocker_user_id', $blocker->id)
            ->where('blocked_user_id', $user->id)
            ->delete();

        return response()->json([
            'message' => 'Player unblocked.',
            'blocked' => false,
        ]);
    }

    private function userData(Request $request, User $user): array
    {
        return [
            'id' => (string) $user->id,
            'name' => $user->name,
            'username' => Str::before($user->email, '@'),
            'avatar_url' => $user->avatarUrl($request->getSchemeAndHttpHost()),
            'games_count' => (int) ($user->joined_matches_count ?? 0),
        ];
    }

    private function ensureDifferentUsers(User $actor, User $target): void
    {
        if ($actor->id === $target->id) {
            throw ValidationException::withMessages([
                'user' => ['You cannot perform this action on your own account.'],
            ]);
        }
    }
}
