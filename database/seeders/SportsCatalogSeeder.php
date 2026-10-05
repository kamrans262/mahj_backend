<?php

namespace Database\Seeders;

use App\Models\MahjMatch;
use App\Models\Sport;
use Illuminate\Database\Seeder;

class SportsCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $mahJongg = Sport::query()->updateOrCreate(
            ['slug' => 'mah-jongg'],
            [
                'name' => 'Mah Jongg',
                'icon_key' => 'generic',
                'sort_order' => 10,
                'is_active' => true,
            ],
        );

        Sport::query()
            ->whereKeyNot($mahJongg->id)
            ->update(['is_active' => false]);

        MahjMatch::query()->update([
            'sport_id' => $mahJongg->id,
            'custom_sport_name' => null,
            'name' => 'Mah Jongg',
        ]);
    }
}
