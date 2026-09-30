<?php

namespace Tests\Feature\Matches;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LocationSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_search_structured_us_locations(): void
    {
        Cache::flush();

        config([
            'services.google_maps.server_key' => 'test-google-key',
            'services.google_maps.geocoding_url' => 'https://maps.googleapis.com/maps/api/geocode/json',
        ]);

        Http::fake([
            'https://maps.googleapis.com/*' => Http::response([
                'status' => 'OK',
                'results' => [
                    [
                        'formatted_address' => 'Central Park, New York, NY 10024, USA',
                        'geometry' => [
                            'location' => [
                                'lat' => 40.785091,
                                'lng' => -73.968285,
                            ],
                        ],
                        'address_components' => [
                            [
                                'long_name' => 'New York',
                                'short_name' => 'New York',
                                'types' => ['locality', 'political'],
                            ],
                            [
                                'long_name' => 'New York',
                                'short_name' => 'NY',
                                'types' => ['administrative_area_level_1', 'political'],
                            ],
                            [
                                'long_name' => '10024',
                                'short_name' => '10024',
                                'types' => ['postal_code'],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/locations/search?q=Central%20Park')
            ->assertOk()
            ->assertJsonPath('data.0.label', 'Central Park, New York, NY 10024, USA')
            ->assertJsonPath('data.0.latitude', 40.785091)
            ->assertJsonPath('data.0.longitude', -73.968285)
            ->assertJsonPath('data.0.city', 'New York')
            ->assertJsonPath('data.0.state', 'New York')
            ->assertJsonPath('data.0.zip_code', '10024');

        Http::assertSent(function (Request $request): bool {
            return str_starts_with(
                $request->url(),
                'https://maps.googleapis.com/maps/api/geocode/json?'
            );
        });
    }
}
