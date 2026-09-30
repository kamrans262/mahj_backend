<?php

namespace Tests\Feature\Admin;

use App\Models\Sport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_sports_catalog(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post('/admin/sports', [
                'name' => 'Racquetball',
                'icon_key' => 'generic',
                'sort_order' => 120,
            ])
            ->assertRedirect();

        $sport = Sport::query()->where('slug', 'racquetball')->firstOrFail();

        $this->actingAs($admin)
            ->get('/admin/sports')
            ->assertOk()
            ->assertSee('value="Racquetball"', false);

        $this->actingAs($admin)
            ->patch('/admin/sports/'.$sport->id, [
                'name' => 'Racquetball',
                'icon_key' => 'tennis',
                'sort_order' => 15,
                'is_active' => '1',
            ])
            ->assertRedirect();

        $sport->refresh();
        $this->assertSame('tennis', $sport->icon_key);
        $this->assertSame(15, $sport->sort_order);
        $this->assertTrue($sport->is_active);
    }
}
