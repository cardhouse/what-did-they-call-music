<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Str;

class SpotifyMatchService
{
    public function __construct(public SpotifyClient $client)
    {
    }

    public function findTrackId(string $title, string $artist): ?string
    {
        $tracks = $this->client->searchTracks($title, $artist);

        if ($tracks === []) {
            return null;
        }

        $normalizedTitle = $this->normalize($title);
        $normalizedArtist = $this->normalize($artist);

        foreach ($tracks as $track) {
            if (!is_array($track)) {
                continue;
            }

            $trackName = $track['name'] ?? null;
            $trackArtists = $track['artists'] ?? null;

            if (!is_string($trackName) || !is_array($trackArtists)) {
                continue;
            }

            $artistNames = collect($trackArtists)
                ->pluck('name')
                ->filter(fn ($name): bool => is_string($name))
                ->map(fn (string $name): string => $this->normalize($name))
                ->all();

            if ($normalizedTitle !== $this->normalize($trackName)) {
                continue;
            }

            if (!in_array($normalizedArtist, $artistNames, true)) {
                continue;
            }

            $trackId = $track['id'] ?? null;

            return is_string($trackId) && $trackId !== '' ? $trackId : null;
        }

        return null;
    }

    private function normalize(string $value): string
    {
        return (string) Str::of($value)
            ->lower()
            ->replaceMatches('/\\(.*?\\)/', '')
            ->replaceMatches('/\\[.*?\\]/', '')
            ->replaceMatches('/\\bfeat\\.?\\b.*/', '')
            ->replaceMatches('/\\bfeaturing\\b.*/', '')
            ->replaceMatches('/\\bft\\.?\\b.*/', '')
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->replaceMatches('/\\s+/', ' ')
            ->trim();
    }
}
