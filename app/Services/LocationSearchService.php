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

        $limit = max(1, min($limit, 8));
        $cacheKey = 'mahj:location-search:'.sha1(mb_strtolower($query)."|".$limit);

        return Cache::remember($cacheKey, now()->addDays(30), function () use ($query, $limit): array {
            try {
                $baseUrl = rtrim((string) config('services.nominatim.base_url'), '/');
                $response = Http::acceptJson()
                    ->withHeaders([
                        'User-Agent' => (string) config('services.nominatim.user_agent'),
                        'Accept-Language' => 'en-US,en;q=0.9',
                    ])
                    ->timeout(6)
                    ->get($baseUrl.'/search', [
                        'q' => $query,
                        'format' => 'jsonv2',
                        'addressdetails' => 1,
                        'limit' => $limit,
                        'countrycodes' => 'us',
                    ]);

                if (! $response->successful() || ! is_array($response->json())) {
                    return [];
                }

                return collect($response->json())
                    ->map(fn (mixed $item): ?array => $this->normalizeResult($item))
                    ->filter()
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
        if (! is_array($item) || ! is_numeric($item['lat'] ?? null) || ! is_numeric($item['lon'] ?? null)) {
            return null;
        }

        $address = is_array($item['address'] ?? null) ? $item['address'] : [];
        $city = $address['city']
            ?? $address['town']
            ?? $address['village']
            ?? $address['municipality']
            ?? '';
        $state = $address['state'] ?? '';
        $zipCode = $address['postcode'] ?? '';

        return [
            'label' => trim((string) ($item['display_name'] ?? $item['name'] ?? '')),
            'latitude' => (float) $item['lat'],
            'longitude' => (float) $item['lon'],
            'city' => (string) $city,
            'state' => (string) $state,
            'zip_code' => (string) $zipCode,
        ];
    }
}
