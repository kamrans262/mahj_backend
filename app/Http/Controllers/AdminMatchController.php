<?php

namespace App\Http\Controllers;

use App\Models\MahjMatch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminMatchController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $status = trim((string) $request->query('status'));

        $matches = MahjMatch::query()
            ->with('host')
            ->withCount('players')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('location_address', 'like', "%{$search}%")
                    ->orWhere('venue_name', 'like', "%{$search}%")
                    ->orWhereHas('host', fn ($query) => $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"));
            }))
            ->when(
                in_array($status, ['open', 'confirmed', 'cancelled', 'completed'], true),
                fn ($query) => $query->where('status', $status),
            )
            ->latest('id')
            ->limit(100)
            ->get();

        return view('admin.matches.index', [
            'matches' => $matches,
            'search' => $search,
            'status' => $status,
            'totalMatches' => MahjMatch::query()->count(),
        ]);
    }

    public function show(MahjMatch $match): View
    {
        $match->load(['host', 'players'])->loadCount('players');

        return view('admin.matches.show', compact('match'));
    }

    public function update(Request $request, MahjMatch $match): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['open', 'confirmed', 'cancelled', 'completed'])],
        ]);

        $match->update([
            'status' => $validated['status'],
            'cancelled_at' => $validated['status'] === 'cancelled'
                ? ($match->cancelled_at ?? now())
                : null,
        ]);

        return back()->with('status', 'Match status updated.');
    }

    public function destroy(MahjMatch $match): RedirectResponse
    {
        $match->delete();

        return redirect()->route('admin.matches')->with('status', 'Match deleted.');
    }
}
