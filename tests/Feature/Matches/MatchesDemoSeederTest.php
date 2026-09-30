<?php

namespace Tests\Feature\Matches;

use App\Models\MahjMatch;
use App\Models\User;
use Database\Seeders\MatchesDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchesDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_creates_reusable_match_data_without_duplicates(): void
    {
        $this->seed(MatchesDemoSeeder::class);
        $this->seed(MatchesDemoSeeder::class);

        $this->assertSame(6, User::query()->where('email', 'like', 'demo.%@mahj.test')->count());
        $this->assertSame(5, MahjMatch::query()->count());

        $counts = MahjMatch::query()
            ->withCount('players')
            ->orderBy('players_count')
            ->pluck('players_count')
            ->all();

        $this->assertSame([1, 2, 2, 3, 4], $counts);
        $this->assertSame(1, MahjMatch::query()->where('status', 'confirmed')->count());
        $this->assertSame(4, MahjMatch::query()->where('status', 'open')->count());
    }
}
