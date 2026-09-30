<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class LocationSearchService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query, int $limit = 6): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return [];
        }

        $apiKey = trim((string) config('services.google_maps.server_key'));
        if ($apiKey === '') {
            return [];
        }

        $limit = max(1, min($limit, 8));
        $cacheKey = 'mahj:google-location-search:v2:'.sha1(mb_strtolower($query)."|".$limit);

        return Cache::remember($cacheKey, now()->addDays(30), function () use ($query, $limit, $apiKey): array {
            try {
                $url = (string) config(
                    'services.google_maps.geocoding_url',
                    'https://maps.googleapis.com/maps/api/geocode/json'
                );

                $response = Http::acceptJson()
                    ->timeout(6)
                    ->get($url, [
                        'address' => $query,
                        'key' => $apiKey,
                    ]);

                if (! $response->successful()) {
                    return [];
                }

                $payload = $response->json();
                if (! is_array($payload) || ($payload['status'] ?? null) !== 'OK') {
                    return [];
                }

                $results = $payload['results'] ?? null;
                if (! is_array($results)) {
                    return [];
                }

                return collect($results)
                    ->map(fn (mixed $item): ?array => $this->normalizeResult($item))
                    ->filter()
                    ->take($limit)
                    ->values()
                    ->all();
            } catch (Throwable) {
                return [];
            }
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    public function firstResult(string $query): ?array
    {
        return $this->search($query, 1)[0] ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeResult(mixed $item): ?array
    {
        if (! is_array($item)) {
            return null;
        }

        $location = $item['geometry']['location'] ?? null;
        if (
            ! is_array($location)
            || ! is_numeric($location['lat'] ?? null)
            || ! is_numeric($location['lng'] ?? null)
        ) {
            return null;
        }

        $components = is_array($item['address_components'] ?? null)
            ? $item['address_components']
            : [];

        return [
            'label' => trim((string) ($item['formatted_address'] ?? '')),
            'latitude' => (float) $location['lat'],
            'longitude' => (float) $location['lng'],
            'city' => $this->component($components, ['locality', 'postal_town']),
            'state' => $this->component($components, ['administrative_area_level_1']),
            'zip_code' => $this->component($components, ['postal_code']),
        ];
    }

    /**
     * @param array<int, mixed> $components
     * @param array<int, string> $wantedTypes
     */
    private function component(array $components, array $wantedTypes): string
    {
        foreach ($components as $component) {
            if (! is_array($component) || ! is_array($component['types'] ?? null)) {
                continue;
            }

            foreach ($wantedTypes as $type) {
                if (in_array($type, $component['types'], true)) {
                    return (string) ($component['long_name'] ?? $component['short_name'] ?? '');
                }
            }
        }

        return '';
    }
}
