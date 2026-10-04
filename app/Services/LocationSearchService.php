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
        $cacheKey = 'mahj:google-location-search:v4:'.sha1(mb_strtolower($query)."|".$limit);

        return Cache::remember($cacheKey, now()->addDays(30), function () use ($query, $limit, $apiKey): array {
            try {
                if ($this->isCoordinateQuery($query)) {
                    return $this->reverseGeocode($query, $limit, $apiKey);
                }

                $places = $this->searchPlaces($query, $limit, $apiKey);
                if ($places !== []) {
                    return $places;
                }

                return $this->geocodeAddress($query, $limit, $apiKey);
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
     * @return array<int, array<string, mixed>>
     */
    private function searchPlaces(string $query, int $limit, string $apiKey): array
    {
        $url = (string) config(
            'services.google_maps.places_text_search_url',
            'https://places.googleapis.com/v1/places:searchText'
        );

        $response = Http::acceptJson()
            ->withHeaders([
                'X-Goog-Api-Key' => $apiKey,
                'X-Goog-FieldMask' => implode(',', [
                    'places.displayName',
                    'places.formattedAddress',
                    'places.location',
                    'places.addressComponents',
                ]),
            ])
            ->timeout(6)
            ->post($url, [
                'textQuery' => $query,
                'pageSize' => $limit,
            ]);

        if (! $response->successful()) {
            return [];
        }

        $payload = $response->json();
        $results = is_array($payload) ? ($payload['places'] ?? null) : null;
        if (! is_array($results)) {
            return [];
        }

        return collect($results)
            ->map(fn (mixed $item): ?array => $this->normalizePlaceResult($item))
            ->filter()
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function geocodeAddress(string $query, int $limit, string $apiKey): array
    {
        return $this->geocode(['address' => $query], $limit, $apiKey);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function reverseGeocode(string $query, int $limit, string $apiKey): array
    {
        return $this->geocode(['latlng' => preg_replace('/\s+/', '', $query)], $limit, $apiKey);
    }

    /**
     * @param array<string, string> $parameters
     * @return array<int, array<string, mixed>>
     */
    private function geocode(array $parameters, int $limit, string $apiKey): array
    {
        $url = (string) config(
            'services.google_maps.geocoding_url',
            'https://maps.googleapis.com/maps/api/geocode/json'
        );

        $response = Http::acceptJson()
            ->timeout(6)
            ->get($url, [
                ...$parameters,
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
            ->map(fn (mixed $item): ?array => $this->normalizeGeocodeResult($item))
            ->filter()
            ->take($limit)
            ->values()
            ->all();
    }

    private function isCoordinateQuery(string $query): bool
    {
        return preg_match(
            '/^-?\d{1,2}(?:\.\d+)?\s*,\s*-?\d{1,3}(?:\.\d+)?$/',
            $query
        ) === 1;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizePlaceResult(mixed $item): ?array
    {
        if (! is_array($item)) {
            return null;
        }

        $location = $item['location'] ?? null;
        if (
            ! is_array($location)
            || ! is_numeric($location['latitude'] ?? null)
            || ! is_numeric($location['longitude'] ?? null)
        ) {
            return null;
        }

        $address = trim((string) ($item['formattedAddress'] ?? ''));
        $displayName = $item['displayName'] ?? null;
        $name = is_array($displayName)
            ? trim((string) ($displayName['text'] ?? ''))
            : '';

        $label = $address;
        if ($name !== '') {
            $label = $address === '' || str_contains(mb_strtolower($address), mb_strtolower($name))
                ? ($address !== '' ? $address : $name)
                : $name.', '.$address;
        }

        if ($label === '') {
            return null;
        }

        $components = is_array($item['addressComponents'] ?? null)
            ? $item['addressComponents']
            : [];

        return [
            'label' => $label,
            'latitude' => (float) $location['latitude'],
            'longitude' => (float) $location['longitude'],
            'city' => $this->newPlacesComponent($components, ['locality', 'postal_town']),
            'state' => $this->newPlacesComponent($components, ['administrative_area_level_1']),
            'zip_code' => $this->newPlacesComponent($components, ['postal_code']),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeGeocodeResult(mixed $item): ?array
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

        $label = trim((string) ($item['formatted_address'] ?? ''));
        if ($label === '') {
            return null;
        }

        return [
            'label' => $label,
            'latitude' => (float) $location['lat'],
            'longitude' => (float) $location['lng'],
            'city' => $this->geocodeComponent($components, ['locality', 'postal_town']),
            'state' => $this->geocodeComponent($components, ['administrative_area_level_1']),
            'zip_code' => $this->geocodeComponent($components, ['postal_code']),
        ];
    }

    /**
     * @param array<int, mixed> $components
     * @param array<int, string> $wantedTypes
     */
    private function newPlacesComponent(array $components, array $wantedTypes): string
    {
        foreach ($components as $component) {
            if (! is_array($component) || ! is_array($component['types'] ?? null)) {
                continue;
            }

            foreach ($wantedTypes as $type) {
                if (in_array($type, $component['types'], true)) {
                    return (string) ($component['longText'] ?? $component['shortText'] ?? '');
                }
            }
        }

        return '';
    }

    /**
     * @param array<int, mixed> $components
     * @param array<int, string> $wantedTypes
     */
    private function geocodeComponent(array $components, array $wantedTypes): string
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
