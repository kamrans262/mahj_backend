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

    public function test_authenticated_user_can_search_structured_global_locations(): void
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
                        'formatted_address' => 'Multan, Punjab, Pakistan',
                        'geometry' => [
                            'location' => [
                                'lat' => 30.1575,
                                'lng' => 71.5249,
                            ],
                        ],
                        'address_components' => [
                            [
                                'long_name' => 'Multan',
                                'short_name' => 'Multan',
                                'types' => ['locality', 'political'],
                            ],
                            [
                                'long_name' => 'Punjab',
                                'short_name' => 'Punjab',
                                'types' => ['administrative_area_level_1', 'political'],
                            ],
                            [
                                'long_name' => '60000',
                                'short_name' => '60000',
                                'types' => ['postal_code'],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/locations/search?q=Multan')
            ->assertOk()
            ->assertJsonPath('data.0.label', 'Multan, Punjab, Pakistan')
            ->assertJsonPath('data.0.latitude', 30.1575)
            ->assertJsonPath('data.0.longitude', 71.5249)
            ->assertJsonPath('data.0.city', 'Multan')
            ->assertJsonPath('data.0.state', 'Punjab')
            ->assertJsonPath('data.0.zip_code', '60000');

        Http::assertSent(function (Request $request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return str_starts_with(
                $request->url(),
                'https://maps.googleapis.com/maps/api/geocode/json?'
            )
                && ($query['address'] ?? null) === 'Multan'
                && ! array_key_exists('components', $query);
        });
    }
}
