<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;

class GeocodingService
{
    public function configured(): bool
    {
        $url = config('services.photon.base_url');
        if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        $parts = parse_url($url);

        return config('maps.geocoding_provider') === 'photon'
            && ($parts['scheme'] ?? '') === 'https'
            && ! isset($parts['user']) && ! isset($parts['pass'])
            && ! isset($parts['query']) && ! isset($parts['fragment']);
    }

    /** @return list<array{label: string, latitude: float, longitude: float, details: array<string, string>}> */
    public function search(string $query): array
    {
        $key = 'geocoding:photon-v2:'.hash('sha256', json_encode([config('maps.geocoding_provider'), config('services.photon.base_url'), $query]));
        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $cached;
        }
        $results = Cache::lock($key.':lock', 15)->get(function () use ($key, $query): array {
            return Cache::remember($key, 600, function () use ($query): array {
                if (RateLimiter::tooManyAttempts('geocoding:photon-provider', 20)) {
                    throw new RuntimeException('Location search is temporarily limited.', 429);
                }
                RateLimiter::hit('geocoding:photon-provider', 60);
                try {
                    $response = Http::acceptJson()->withUserAgent('LIMCOMA-Attendance/1.0')->connectTimeout(3)->timeout(8)->withOptions(['allow_redirects' => false])
                        ->get(rtrim(config('services.photon.base_url'), '/').'/api/', [
                            'q' => $query, 'limit' => 5, 'lang' => 'en',
                        ]);
                } catch (ConnectionException) {
                    throw new RuntimeException('Location search timed out.', 504);
                }
                if ($response->status() === 429) {
                    throw new RuntimeException('Location search is temporarily limited.', 429);
                }
                if (! $response->successful() || $response->json('type') !== 'FeatureCollection' || ! is_array($response->json('features')) || ! array_is_list($response->json('features'))) {
                    throw new RuntimeException('Location search is temporarily unavailable.', 503);
                }
                $results = [];
                foreach (array_slice($response->json('features'), 0, 5) as $feature) {
                    if (! is_array($feature) || ($feature['geometry']['type'] ?? null) !== 'Point') {
                        continue;
                    }
                    $coordinates = $feature['geometry']['coordinates'] ?? null;
                    $properties = $feature['properties'] ?? null;
                    if (! is_array($coordinates) || ! array_is_list($coordinates) || count($coordinates) < 2 || ! is_numeric($coordinates[0]) || ! is_numeric($coordinates[1]) || ! is_array($properties)) {
                        continue;
                    }
                    $longitude = (float) $coordinates[0];
                    $latitude = (float) $coordinates[1];
                    if (! is_finite($latitude) || ! is_finite($longitude) || abs($latitude) > 90 || abs($longitude) > 180) {
                        continue;
                    }
                    $parts = [];
                    foreach (['name', 'housenumber', 'street', 'city', 'county', 'state', 'postcode', 'country'] as $field) {
                        if (is_string($properties[$field] ?? null) && trim($properties[$field]) !== '') {
                            $parts[] = trim($properties[$field]);
                        }
                    }
                    if ($parts === []) {
                        continue;
                    }
                    $results[] = ['label' => mb_substr(implode(', ', array_unique($parts)), 0, 500), 'latitude' => $latitude, 'longitude' => $longitude, 'details' => $this->branchDetails($properties)];
                }

                return $results;
            });
        });
        if ($results === false) {
            throw new RuntimeException('A location search is already in progress.', 503);
        }

        return $results;
    }

    /** @param array<string, mixed> $properties
     * @return array<string, string>
     */
    private function branchDetails(array $properties): array
    {
        $text = fn (string $field): string => is_string($properties[$field] ?? null) ? trim($properties[$field]) : '';
        $city = $text('city') ?: ($text('town') ?: $text('municipality'));
        $province = $text('state');
        if (preg_match('/^(region\\b|calabarzon$|mimaropa$|central luzon$|national capital region$|metro manila$)/i', $province)) {
            $province = $text('county');
        }
        $street = $text('street') ?: (($properties['osm_key'] ?? null) === 'highway' ? $text('name') : '');
        $address = $street !== '' ? trim($text('housenumber').' '.$street) : '';
        $parts = array_filter([$address, $text('district'), $text('locality')], fn (string $part): bool => $part !== '' && ! in_array($part, [$city, $province, $text('country')], true));
        $details = ['address' => implode(', ', array_unique($parts)), 'city' => $city, 'province' => $province, 'postal_code' => $text('postcode')];
        $namedEstablishment = in_array($properties['osm_key'] ?? null, ['office', 'shop', 'building', 'craft', 'healthcare'], true)
            || (($properties['osm_key'] ?? null) === 'amenity' && in_array($properties['osm_value'] ?? null, ['bank', 'school', 'hospital', 'clinic', 'pharmacy', 'post_office', 'townhall', 'community_centre', 'college', 'university'], true));
        if ($namedEstablishment) {
            $details['branch_name'] = $text('name');
        }
        $limits = ['branch_name' => 150, 'address' => 255, 'city' => 100, 'province' => 100, 'postal_code' => 20];
        foreach ($details as $field => $value) {
            $details[$field] = mb_substr($value, 0, $limits[$field]);
        }

        return array_filter($details, fn (string $value): bool => $value !== '');
    }
}
