<?php

namespace App\Http\Controllers;

use App\Models\Sport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminSportController extends Controller
{
    private const ICON_KEYS = [
        'football',
        'basketball',
        'baseball',
        'soccer',
        'tennis',
        'volleyball',
        'hockey',
        'pickleball',
        'golf',
        'softball',
        'lacrosse',
        'generic',
    ];

    public function index(): View
    {
        return view('admin.sports.index', [
            'sports' => Sport::query()->orderBy('sort_order')->orderBy('name')->get(),
            'iconKeys' => self::ICON_KEYS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'icon_key' => ['required', Rule::in(self::ICON_KEYS)],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
        ]);

        $slug = Str::slug($validated['name']);

        if ($slug === '' || Sport::query()->where('slug', $slug)->exists()) {
            return back()->withErrors([
                'name' => 'Use a unique sport name.',
            ])->withInput();
        }

        Sport::query()->create([
            'name' => trim($validated['name']),
            'slug' => $slug,
            'icon_key' => $validated['icon_key'],
            'sort_order' => $validated['sort_order'],
            'is_active' => true,
        ]);

        return back()->with('status', 'Sport added.');
    }

    public function update(Request $request, Sport $sport): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'icon_key' => ['required', Rule::in(self::ICON_KEYS)],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $sport->update([
            'name' => trim($validated['name']),
            'icon_key' => $validated['icon_key'],
            'sort_order' => $validated['sort_order'],
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('status', 'Sport updated.');
    }
}
