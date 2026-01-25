<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SpotifyMatchService
{
    public function __construct(public SpotifyClient $client) {}

    public function findTrackId(string $title, string $artist): ?string
    {
        if (trim($title) === '' || trim($artist) === '') {
            return null;
        }

        // Step 1: Try strict search (track + artist)
        $tracks = $this->client->searchTracks($title, $artist);
        $result = $this->findMatchingTrack($tracks, $title, $artist);

        if ($result !== null) {
            return $result;
        }

        // Step 2: Fallback - search by title only, apply fuzzy artist matching
        $tracks = $this->client->searchTracksByTitle($title);

        return $this->findMatchingTrack($tracks, $title, $artist);
    }

    /**
     * Find a matching track from search results.
     *
     * @param  array<int, mixed>  $tracks
     */
    private function findMatchingTrack(array $tracks, string $title, string $artist): ?string
    {
        if ($tracks === []) {
            return null;
        }

        $normalizedTitle = $this->normalizeTitle($title);
        $normalizedArtist = $this->normalizeArtist($artist);

        foreach ($tracks as $track) {
            if (! is_array($track)) {
                continue;
            }

            $trackName = $track['name'] ?? null;
            $trackArtists = $track['artists'] ?? null;

            if (! is_string($trackName) || ! is_array($trackArtists)) {
                continue;
            }

            // Title must match strictly
            if ($normalizedTitle !== $this->normalizeTitle($trackName)) {
                continue;
            }

            $spotifyArtistNames = collect($trackArtists)
                ->pluck('name')
                ->filter(fn ($name): bool => is_string($name))
                ->map(fn (string $name): string => $this->normalizeArtist($name))
                ->all();

            if ($this->artistMatches($normalizedArtist, $spotifyArtistNames)) {
                $trackId = $track['id'] ?? null;

                return is_string($trackId) && $trackId !== '' ? $trackId : null;
            }
        }

        return null;
    }

    /**
     * Check if the artist matches any of the Spotify artist names using fuzzy matching.
     *
     * @param  array<int, string>  $spotifyArtistNames
     */
    private function artistMatches(string $normalizedArtist, array $spotifyArtistNames): bool
    {
        foreach ($spotifyArtistNames as $spotifyArtist) {
            // 1. Exact match
            if ($normalizedArtist === $spotifyArtist) {
                Log::debug('Spotify exact artist match', [
                    'input' => $normalizedArtist,
                    'spotify' => $spotifyArtist,
                ]);

                return true;
            }

            // 1b. Compact match (handles cases like "P!nk" vs "Pink")
            $compactInput = $this->compactValue($normalizedArtist);
            $compactSpotify = $this->compactValue($spotifyArtist);

            if ($compactInput !== '' && $compactInput === $compactSpotify) {
                Log::debug('Spotify compact artist match', [
                    'input' => $normalizedArtist,
                    'spotify' => $spotifyArtist,
                ]);

                return true;
            }

            // 1c. Short name match (handles short stylized names like "P!nk")
            if ($this->isShortArtistMatch($normalizedArtist, $spotifyArtist)) {
                Log::debug('Spotify short artist match', [
                    'input' => $normalizedArtist,
                    'spotify' => $spotifyArtist,
                ]);

                return true;
            }

            // 2. Contains match (with length constraints to prevent false positives)
            if ($this->isContainsMatch($normalizedArtist, $spotifyArtist)) {
                Log::debug('Spotify contains artist match', [
                    'input' => $normalizedArtist,
                    'spotify' => $spotifyArtist,
                ]);

                return true;
            }

            // 3. Similarity match using configurable threshold
            $similarity = $this->calculateSimilarity($normalizedArtist, $spotifyArtist);
            $threshold = $this->getMatchThreshold();

            if ($similarity >= $threshold) {
                Log::debug('Spotify similarity artist match', [
                    'input' => $normalizedArtist,
                    'spotify' => $spotifyArtist,
                    'similarity' => $similarity,
                    'threshold' => $threshold,
                ]);

                return true;
            }
        }

        return false;
    }

    /**
     * Check if one string contains the other with length constraints.
     * Prevents matches like "Pink" matching "Pink Floyd".
     */
    private function isContainsMatch(string $needle, string $haystack): bool
    {
        $needleLen = strlen($needle);
        $haystackLen = strlen($haystack);

        // Minimum length to prevent very short matches
        if ($needleLen < 3) {
            return false;
        }

        // Prevent huge length differences (more than 50% difference)
        $lengthDiff = abs($needleLen - $haystackLen);
        $maxAllowedDiff = (int) ceil(max($needleLen, $haystackLen) * 0.5);

        if ($lengthDiff > $maxAllowedDiff) {
            return false;
        }

        return str_contains($haystack, $needle) || str_contains($needle, $haystack);
    }

    /**
     * Calculate similarity between two strings using Levenshtein distance.
     */
    private function calculateSimilarity(string $a, string $b): float
    {
        $maxLength = max(strlen($a), strlen($b));

        if ($maxLength === 0) {
            return 100.0;
        }

        $distance = levenshtein($a, $b);

        return (1 - ($distance / $maxLength)) * 100;
    }

    /**
     * Compact a normalized string by removing spaces.
     */
    private function compactValue(string $value): string
    {
        return (string) Str::of($value)
            ->replace(' ', '')
            ->trim();
    }

    /**
     * Handle short stylized artist names (4 chars or fewer) with small edits.
     */
    private function isShortArtistMatch(string $a, string $b): bool
    {
        $compactA = $this->compactValue($a);
        $compactB = $this->compactValue($b);

        $maxLength = max(strlen($compactA), strlen($compactB));

        if ($maxLength === 0 || $maxLength > 4) {
            return false;
        }

        return levenshtein($compactA, $compactB) <= 1;
    }

    /**
     * Get the configured match threshold, clamped between 0-100.
     */
    private function getMatchThreshold(): int
    {
        $threshold = (int) config('spotify.artist_match_threshold', 80);

        return max(0, min(100, $threshold));
    }

    /**
     * Normalize a title for comparison.
     */
    private function normalizeTitle(string $value): string
    {
        return (string) Str::of($value)
            ->lower()
            ->replaceMatches('/\\(.*?\\)/', '')      // Remove parentheses content
            ->replaceMatches('/\\[.*?\\]/', '')      // Remove bracket content
            ->replaceMatches('/\\bfeat\\.?\\b.*/', '') // Remove featuring
            ->replaceMatches('/\\bfeaturing\\b.*/', '')
            ->replaceMatches('/\\bft\\.?\\b.*/', '')
            ->replace('&', ' and ')                   // Normalize ampersand
            ->replaceMatches('/[^a-z0-9]+/', ' ')    // Remove special chars
            ->replaceMatches('/\\s+/', ' ')          // Collapse whitespace
            ->trim();
    }

    /**
     * Normalize an artist name for comparison.
     * Includes additional artist-specific normalization rules.
     */
    private function normalizeArtist(string $value): string
    {
        $normalized = $this->normalizeTitle($value);

        // Remove common prefixes for artist comparison
        return (string) Str::of($normalized)
            ->replaceMatches('/^the\\s+/', '')  // Remove leading "the "
            ->trim();
    }
}
