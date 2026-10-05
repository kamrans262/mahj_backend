<?php

namespace App\Http\Controllers;

use App\Models\Sport;
use App\Models\SportBannerImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminSportController extends Controller
{
    private const SPORT_SLUG = 'mah-jongg';

    private const ICON_KEYS = [
        'generic',
    ];

    public function index(): View
    {
        return view('admin.sports.index', [
            'sports' => Sport::query()
                ->with('bannerImages')
                ->where('slug', self::SPORT_SLUG)
                ->orderBy('sort_order')
                ->get(),
            'iconKeys' => self::ICON_KEYS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $existing = Sport::query()->where('slug', self::SPORT_SLUG)->first();
        if ($existing !== null) {
            return back()->with('status', 'Mah Jongg is already configured.');
        }

        $validated = $this->validatedSport($request);

        $sport = Sport::query()->create([
            'name' => 'Mah Jongg',
            'slug' => self::SPORT_SLUG,
            'icon_key' => 'generic',
            'sort_order' => $validated['sort_order'],
            'is_active' => true,
        ]);

        $this->storeBannerImages($request, $sport);

        return back()->with('status', 'Mah Jongg configured.');
    }

    public function update(Request $request, Sport $sport): RedirectResponse
    {
        if ($sport->slug !== self::SPORT_SLUG) {
            abort(404);
        }

        $validated = $this->validatedSport($request);

        $removeIds = collect($validated['remove_banner_ids'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        if ($removeIds->isNotEmpty()) {
            $images = SportBannerImage::query()
                ->where('sport_id', $sport->id)
                ->whereIn('id', $removeIds)
                ->get();

            foreach ($images as $image) {
                Storage::disk('public')->delete($image->image_path);
                $image->delete();
            }
        }

        $this->storeBannerImages($request, $sport);

        $sport->update([
            'name' => 'Mah Jongg',
            'icon_key' => 'generic',
            'sort_order' => $validated['sort_order'],
            'is_active' => true,
        ]);

        return back()->with('status', 'Mah Jongg settings updated.');
    }

    private function validatedSport(Request $request): array
    {
        return $request->validate([
            'name' => ['nullable', Rule::in(['Mah Jongg'])],
            'icon_key' => ['nullable', Rule::in(self::ICON_KEYS)],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'banner_images' => ['nullable', 'array', 'max:20'],
            'banner_images.*' => [
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
            'remove_banner_ids' => ['nullable', 'array'],
            'remove_banner_ids.*' => ['integer'],
        ]);
    }

    private function storeBannerImages(Request $request, Sport $sport): void
    {
        $files = $request->file('banner_images', []);
        if (! is_array($files) || $files === []) {
            return;
        }

        $nextOrder = ((int) $sport->bannerImages()->max('sort_order')) + 1;

        foreach ($files as $file) {
            $path = $file->store('sports/banners', 'public');

            $sport->bannerImages()->create([
                'image_path' => $path,
                'sort_order' => $nextOrder++,
            ]);
        }
    }
}
