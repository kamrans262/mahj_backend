<?php

namespace Tests\Feature\Matches;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LocationSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_search_structured_us_locations(): void
    {
        Cache::flush();
        Http::fake([
            '*' => Http::response([
                [
                    'display_name' => 'Central Park, Manhattan, New York, NY 10024, United States',
                    'lat' => '40.785091',
                    'lon' => '-73.968285',
                    'address' => [
                        'city' => 'New York',
                        'state' => 'New York',
                        'postcode' => '10024',
                    ],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/locations/search?q=Central%20Park')
            ->assertOk()
            ->assertJsonPath('data.0.label', 'Central Park, Manhattan, New York, NY 10024, United States')
            ->assertJsonPath('data.0.latitude', 40.785091)
            ->assertJsonPath('data.0.longitude', -73.968285)
            ->assertJsonPath('data.0.city', 'New York')
            ->assertJsonPath('data.0.state', 'New York')
            ->assertJsonPath('data.0.zip_code', '10024');

        Http::assertSentCount(1);
    }
}
