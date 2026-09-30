<?php

namespace Database\Seeders;

use App\Models\MahjMatch;
use App\Models\Sport;
use Illuminate\Database\Seeder;

class SportsCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $sports = [
            ['name' => 'American Football', 'slug' => 'american-football', 'icon_key' => 'football', 'sort_order' => 10],
            ['name' => 'Basketball', 'slug' => 'basketball', 'icon_key' => 'basketball', 'sort_order' => 20],
            ['name' => 'Baseball', 'slug' => 'baseball', 'icon_key' => 'baseball', 'sort_order' => 30],
            ['name' => 'Soccer', 'slug' => 'soccer', 'icon_key' => 'soccer', 'sort_order' => 40],
            ['name' => 'Tennis', 'slug' => 'tennis', 'icon_key' => 'tennis', 'sort_order' => 50],
            ['name' => 'Volleyball', 'slug' => 'volleyball', 'icon_key' => 'volleyball', 'sort_order' => 60],
            ['name' => 'Ice Hockey', 'slug' => 'ice-hockey', 'icon_key' => 'hockey', 'sort_order' => 70],
            ['name' => 'Pickleball', 'slug' => 'pickleball', 'icon_key' => 'pickleball', 'sort_order' => 80],
            ['name' => 'Golf', 'slug' => 'golf', 'icon_key' => 'golf', 'sort_order' => 90],
            ['name' => 'Softball', 'slug' => 'softball', 'icon_key' => 'softball', 'sort_order' => 100],
            ['name' => 'Lacrosse', 'slug' => 'lacrosse', 'icon_key' => 'lacrosse', 'sort_order' => 110],
        ];

        foreach ($sports as $attributes) {
            Sport::query()->firstOrCreate(
                ['slug' => $attributes['slug']],
                $attributes + ['is_active' => true],
            );
        }

        $allSports = Sport::query()->get();
        $knownSports = $allSports->keyBy(fn (Sport $sport) => strtolower($sport->name));
        $sportsBySlug = $allSports->keyBy('slug');
        $legacyAliases = [
            'football' => 'american-football',
            'basket ball' => 'basketball',
            'icehockey' => 'ice-hockey',
        ];

        MahjMatch::query()
            ->whereNull('sport_id')
            ->whereNull('custom_sport_name')
            ->chunkById(100, function ($matches) use ($knownSports, $sportsBySlug, $legacyAliases): void {
                foreach ($matches as $match) {
                    $legacyName = strtolower(trim((string) $match->name));
                    $sport = $knownSports->get($legacyName);
                    if ($sport === null && isset($legacyAliases[$legacyName])) {
                        $sport = $sportsBySlug->get($legacyAliases[$legacyName]);
                    }

                    if ($sport !== null) {
                        $match->update([
                            'sport_id' => $sport->id,
                            'name' => $sport->name,
                        ]);
                    } elseif (filled($match->name)) {
                        $match->update([
                            'custom_sport_name' => trim((string) $match->name),
                        ]);
                    }
                }
            });
    }
}
