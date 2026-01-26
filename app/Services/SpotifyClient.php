<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SpotifyClient
{
    /**
     * Search tracks by title and artist (strict search).
     *
     * @return array<int, array<string, mixed>>
     */
    public function searchTracks(string $title, string $artist): array
    {
        if (trim($title) === '' || trim($artist) === '') {
            return [];
        }

        $token = $this->getAccessToken();

        if (! $token) {
            return [];
        }

        $query = sprintf('track:"%s" artist:"%s"', $title, $artist);
        $cacheKey = 'spotify.search.'.sha1($query);

        return Cache::remember($cacheKey, now()->addDay(), function () use ($token, $query): array {
            $response = Http::withToken($token)->get($this->apiBaseUrl().'/v1/search', [
                'type' => 'track',
                'limit' => 10,
                'q' => $query,
            ]);

            if (! $response->successful()) {
                return [];
            }

            $items = $response->json('tracks.items');

            return is_array($items) ? $items : [];
        });
    }

    /**
     * Search tracks by title only (fallback for fuzzy artist matching).
     *
     * @return array<int, array<string, mixed>>
     */
    public function searchTracksByTitle(string $title): array
    {
        if (trim($title) === '') {
            return [];
        }

        $token = $this->getAccessToken();

        if (! $token) {
            return [];
        }

        $query = sprintf('track:"%s"', $title);
        $cacheKey = 'spotify.search.title.'.sha1($query);

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($token, $query): array {
            $response = Http::withToken($token)->get($this->apiBaseUrl().'/v1/search', [
                'type' => 'track',
                'limit' => 20,
                'q' => $query,
            ]);

            if (! $response->successful()) {
                return [];
            }

            $items = $response->json('tracks.items');

            return is_array($items) ? $items : [];
        });
    }

    private function getAccessToken(): ?string
    {
        $clientId = (string) config('spotify.client_id');
        $clientSecret = (string) config('spotify.client_secret');

        if ($clientId === '' || $clientSecret === '') {
            return null;
        }

        $cacheKey = 'spotify.access_token';
        $cached = Cache::get($cacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = Http::asForm()
            ->withBasicAuth($clientId, $clientSecret)
            ->post($this->accountsBaseUrl().'/api/token', [
                'grant_type' => 'client_credentials',
            ]);

        if (! $response->successful()) {
            return null;
        }

        $token = $response->json('access_token');

        if (! is_string($token) || $token === '') {
            return null;
        }

        $expiresIn = (int) $response->json('expires_in', 3600);
        $ttl = max(60, $expiresIn - 60);

        Cache::put($cacheKey, $token, now()->addSeconds($ttl));

        return $token;
    }

    private function accountsBaseUrl(): string
    {
        return rtrim((string) config('spotify.accounts_base_url'), '/');
    }

    private function apiBaseUrl(): string
    {
        return rtrim((string) config('spotify.api_base_url'), '/');
    }
}
