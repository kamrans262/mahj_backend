<?php

namespace App\Http\Controllers;

use App\Models\MahjMatch;
use App\Models\Sport;
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
            ->with(['host', 'sport'])
            ->withCount('players')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('location_address', 'like', "%{$search}%")
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
        $match->load(['host', 'players', 'sport'])->loadCount('players');

        return view('admin.matches.show', [
            'match' => $match,
            'sports' => Sport::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(Request $request, MahjMatch $match): RedirectResponse
    {
        $validated = $request->validate([
            'sport_id' => ['nullable', 'integer', 'exists:sports,id', 'required_without:custom_sport_name'],
            'custom_sport_name' => ['nullable', 'string', 'max:100', 'required_without:sport_id'],
            'location_address' => ['required', 'string', 'max:255'],
            'venue_name' => ['nullable', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'starts_at' => ['required', 'date'],
            'status' => ['required', Rule::in(['open', 'confirmed', 'cancelled', 'completed'])],
            'is_public' => ['nullable', 'boolean'],
            'is_invite_only' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'featured_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
        ]);

        $inviteOnly = $request->boolean('is_invite_only');
        $sport = isset($validated['sport_id'])
            ? Sport::query()->find($validated['sport_id'])
            : null;
        $customSportName = filled($validated['custom_sport_name'] ?? null)
            ? trim((string) $validated['custom_sport_name'])
            : null;
        $displayName = $sport?->name ?? $customSportName;

        $match->update([
            'name' => $displayName,
            'sport_id' => $sport?->id,
            'custom_sport_name' => $sport === null ? $customSportName : null,
            'location_address' => trim($validated['location_address']),
            'venue_name' => filled($validated['venue_name'] ?? null)
                ? trim((string) $validated['venue_name'])
                : null,
            'notes' => filled($validated['notes'] ?? null)
                ? trim((string) $validated['notes'])
                : null,
            'starts_at' => $validated['starts_at'],
            'status' => $validated['status'],
            'is_public' => $inviteOnly ? false : $request->boolean('is_public'),
            'is_invite_only' => $inviteOnly,
            'is_featured' => $request->boolean('is_featured'),
            'featured_order' => (int) ($validated['featured_order'] ?? 0),
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'cancelled_at' => $validated['status'] === 'cancelled'
                ? ($match->cancelled_at ?? now())
                : null,
        ]);

        return back()->with('status', 'Match updated.');
    }

    public function destroy(MahjMatch $match): RedirectResponse
    {
        $match->delete();

        return redirect()->route('admin.matches')->with('status', 'Match deleted.');
    }
}
