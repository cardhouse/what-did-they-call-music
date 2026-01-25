<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SpotifyClient
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function searchTracks(string $title, string $artist): array
    {
        $token = $this->getAccessToken();

        if (!$token) {
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

            if (!$response->successful()) {
                return [];
            }

            $items = $response->json('tracks.items');

            return is_array($items) ? $items : [];
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function searchAlbums(string $albumName): array
    {
        $token = $this->getAccessToken();

        if (!$token) {
            return [];
        }

        $cacheKey = 'spotify.album.search.'.sha1($albumName);

        return Cache::remember($cacheKey, now()->addWeek(), function () use ($token, $albumName): array {
            $response = Http::withToken($token)->get($this->apiBaseUrl().'/v1/search', [
                'type' => 'album',
                'limit' => 5,
                'q' => $albumName,
            ]);

            if (!$response->successful()) {
                return [];
            }

            $items = $response->json('albums.items');

            return is_array($items) ? $items : [];
        });
    }

    /**
     * Get the largest album artwork URL from Spotify search results.
     */
    public function findAlbumArtwork(string $albumName): ?string
    {
        $results = $this->searchAlbums($albumName);

        if (empty($results)) {
            return null;
        }

        $normalizedQuery = $this->normalize($albumName);

        foreach ($results as $album) {
            $spotifyAlbumName = $album['name'] ?? '';
            $normalizedSpotifyName = $this->normalize($spotifyAlbumName);

            if (str_contains($normalizedSpotifyName, $normalizedQuery) ||
                str_contains($normalizedQuery, $normalizedSpotifyName)) {
                $images = $album['images'] ?? [];

                if (!empty($images)) {
                    usort($images, fn ($a, $b) => ($b['height'] ?? 0) - ($a['height'] ?? 0));

                    return $images[0]['url'] ?? null;
                }
            }
        }

        return null;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower($value);
        $value = preg_replace('/[^\p{L}\p{N}\s]/u', '', $value);

        return trim((string) preg_replace('/\s+/', ' ', (string) $value));
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

        if (!$response->successful()) {
            return null;
        }

        $token = $response->json('access_token');

        if (!is_string($token) || $token === '') {
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
