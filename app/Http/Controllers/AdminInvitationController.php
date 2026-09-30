<?php

namespace App\Http\Controllers;

use App\Models\MatchInvitation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminInvitationController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['pending', 'accepted', 'declined'])],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $status = trim((string) ($validated['status'] ?? ''));

        $query = MatchInvitation::query()
            ->with([
                'match.sport',
                'match.host',
                'inviter',
                'invitee',
            ])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.$search.'%';

                $query->where(function (Builder $query) use ($like): void {
                    $query
                        ->whereHas('inviter', fn (Builder $userQuery): Builder => $userQuery
                            ->where('name', 'like', $like)
                            ->orWhere('email', 'like', $like))
                        ->orWhereHas('invitee', fn (Builder $userQuery): Builder => $userQuery
                            ->where('name', 'like', $like)
                            ->orWhere('email', 'like', $like))
                        ->orWhereHas('match', function (Builder $matchQuery) use ($like): void {
                            $matchQuery
                                ->where('name', 'like', $like)
                                ->orWhere('custom_sport_name', 'like', $like)
                                ->orWhere('venue_name', 'like', $like)
                                ->orWhere('location_address', 'like', $like);
                        });
                });
            })
            ->when(
                $status !== '',
                fn (Builder $query): Builder => $query->where('status', $status),
            );

        $invitations = (clone $query)
            ->latest('id')
            ->limit(100)
            ->get();

        return view('admin.invitations.index', [
            'invitations' => $invitations,
            'search' => $search,
            'status' => $status,
            'totalInvitations' => MatchInvitation::query()->count(),
            'pendingInvitations' => MatchInvitation::query()->where('status', 'pending')->count(),
            'acceptedInvitations' => MatchInvitation::query()->where('status', 'accepted')->count(),
            'declinedInvitations' => MatchInvitation::query()->where('status', 'declined')->count(),
        ]);
    }
}
