<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Album;
use App\Models\Artist;
use App\Models\Song;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class FandomScraper
{
    private Client $client;

    private const BASE_URL = 'https://nowmusic-us.fandom.com';

    private const USER_AGENT = 'WhatDidTheyCallMusic/1.0 (Educational Project)';

    private const ALBUM_LIST_URL = '/wiki/List_of_NOW_That%27s_What_I_Call_Music!_(US)_albums';

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => self::BASE_URL,
            'timeout' => 30,
            'headers' => [
                'User-Agent' => self::USER_AGENT,
            ],
        ]);
    }

    /**
     * Get the list of all US NOW albums from the Fandom wiki.
     *
     * @return Collection<int, array{number: int|null, name: string, release_date: string|null, type: string, fandom_url: string}>
     */
    public function getAlbumList(): Collection
    {
        try {
            $response = $this->client->get(self::ALBUM_LIST_URL);
            $html = (string) $response->getBody();

            return $this->parseAlbumList($html);
        } catch (GuzzleException $e) {
            Log::warning('Failed to fetch Fandom album list page', [
                'error' => $e->getMessage(),
            ]);

            return collect();
        }
    }

    /**
     * Parse the album list page to extract album metadata and URLs.
     *
     * @return Collection<int, array{number: int|null, name: string, release_date: string|null, type: string, fandom_url: string}>
     */
    private function parseAlbumList(string $html): Collection
    {
        $albums = collect();

        // Find the "Main series" table
        if (! preg_match('/<h2[^>]*>.*?Main series.*?<\/h2>.*?<table[^>]*class="[^"]*fandom-table[^"]*"[^>]*>(.*?)<\/table>/si', $html, $tableMatch)) {
            Log::warning('Could not find Main series table on Fandom album list page');

            return $albums;
        }

        $tableHtml = $tableMatch[1];

        // Parse table rows
        preg_match_all('/<tr[^>]*>(.*?)<\/tr>/si', $tableHtml, $rows);

        foreach ($rows[1] as $rowIndex => $row) {
            // Skip header row
            if ($rowIndex === 0) {
                continue;
            }

            // Extract cells
            preg_match_all('/<td[^>]*>(.*?)<\/td>/si', $row, $cells);

            if (count($cells[1]) < 3) {
                continue;
            }

            // Cell 0: Album number
            $numberText = $this->cleanText($cells[1][0]);
            $number = is_numeric($numberText) ? (int) $numberText : null;

            // Cell 2: Album title with link
            $titleCell = $cells[1][2];
            if (preg_match('/<a[^>]*href="([^"]*)"[^>]*>(.*?)<\/a>/i', $titleCell, $linkMatch)) {
                $fandomUrl = $linkMatch[1];
                $name = $this->cleanText($linkMatch[2]);
            } else {
                // No link found, skip this album
                continue;
            }

            // Cell 3: Release date
            $releaseDateText = $this->cleanText($cells[1][3]);
            $releaseDate = $this->parseReleaseDate($releaseDateText);

            $albums->push([
                'number' => $number,
                'name' => $name,
                'release_date' => $releaseDate,
                'type' => 'regular',
                'fandom_url' => $fandomUrl,
            ]);
        }

        return $albums;
    }

    /**
     * Parse release date from various formats.
     */
    private function parseReleaseDate(string $dateText): ?string
    {
        try {
            // Try to parse date like "October 27, 1998" or "July 27, 1999"
            $date = \DateTime::createFromFormat('F j, Y', $dateText);
            if ($date) {
                return $date->format('Y-m-d');
            }

            // Try other common formats
            $date = \DateTime::createFromFormat('M j, Y', $dateText);
            if ($date) {
                return $date->format('Y-m-d');
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Scrape track listing from a Fandom album page.
     *
     * @return array<int, array{track_number: int, title: string, artist: string, chart_position: string|null}>
     */
    public function scrapeTrackListing(string $fandomUrl): array
    {
        try {
            $response = $this->client->get($fandomUrl);
            $html = (string) $response->getBody();

            return $this->parseTrackListing($html);
        } catch (GuzzleException $e) {
            Log::warning("Failed to fetch Fandom page: {$fandomUrl}", [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Parse track listing from Fandom HTML content.
     *
     * @return array<int, array{track_number: int, title: string, artist: string, chart_position: string|null}>
     */
    private function parseTrackListing(string $html): array
    {
        $tracks = [];

        // Find the Tracklist section and the fandom-table that follows it
        if (! preg_match('/<h2[^>]*>.*?<span[^>]*id="Tracklist"[^>]*>.*?<\/h2>.*?<table[^>]*class="[^"]*fandom-table[^"]*"[^>]*>(.*?)<\/table>/si', $html, $tableMatch)) {
            Log::warning('Could not find Tracklist table on Fandom album page');

            return [];
        }

        $tableHtml = $tableMatch[1];

        // Parse table rows
        preg_match_all('/<tr[^>]*>(.*?)<\/tr>/si', $tableHtml, $rows);

        foreach ($rows[1] as $rowIndex => $row) {
            // Skip header row
            if ($rowIndex === 0) {
                continue;
            }

            // Extract cells
            preg_match_all('/<td[^>]*>(.*?)<\/td>/si', $row, $cells);

            if (count($cells[1]) < 3) {
                continue;
            }

            // Cell 0: Track number
            $trackNumText = $this->cleanText($cells[1][0]);
            if (! is_numeric($trackNumText)) {
                continue;
            }
            $trackNumber = (int) $trackNumText;

            // Cell 1: Song title
            $title = $this->cleanText($cells[1][1]);

            // Cell 2: Artist
            $artist = $this->cleanText($cells[1][2]);

            // Cell 3: Chart position (optional)
            $chartPosition = null;
            if (isset($cells[1][3])) {
                $chartPosText = $this->cleanText($cells[1][3]);
                if ($chartPosText && $chartPosText !== '---') {
                    $chartPosition = $chartPosText;
                }
            }

            if ($title && $artist) {
                $tracks[] = [
                    'track_number' => $trackNumber,
                    'title' => $title,
                    'artist' => $artist,
                    'chart_position' => $chartPosition,
                ];
            }
        }

        return $tracks;
    }

    /**
     * Scrape album cover art URL from a Fandom album page.
     */
    public function scrapeAlbumCover(string $fandomUrl): ?string
    {
        try {
            $response = $this->client->get($fandomUrl);
            $html = (string) $response->getBody();

            return $this->parseAlbumCover($html);
        } catch (GuzzleException $e) {
            Log::warning("Failed to fetch Fandom page for cover art: {$fandomUrl}", [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Parse album cover image URL from Fandom HTML content.
     */
    private function parseAlbumCover(string $html): ?string
    {
        // First try og:image meta tag - this is what Fandom exposes for social sharing
        // and is most likely to work without hotlink protection issues
        if (preg_match('/<meta[^>]*property="og:image"[^>]*content="([^"]+)"[^>]*>/i', $html, $match)) {
            return $match[1];
        }

        // Fallback: Look for the main album image in the infobox (pi-image class)
        if (preg_match('/<figure[^>]*class="[^"]*pi-image[^"]*"[^>]*>.*?<img[^>]*src="([^"]+)"[^>]*>/si', $html, $match)) {
            $url = $match[1];

            // Use a medium size for better display
            $url = preg_replace('/\/scale-to-width-down\/\d+/', '/scale-to-width-down/400', $url);

            return $url;
        }

        return null;
    }

    /**
     * Get the Fandom URL for an album by its number.
     */
    public function getAlbumFandomUrl(int $albumNumber): ?string
    {
        return "/wiki/NOW_That%27s_What_I_Call_Music!_{$albumNumber}";
    }

    /**
     * Clean HTML text, removing tags and normalizing whitespace.
     */
    private function cleanText(string $html): string
    {
        // Remove links but keep text
        $text = preg_replace('/<a[^>]*>([^<]*)<\/a>/i', '$1', $html);

        // Remove all other HTML tags
        $text = strip_tags($text ?? '');

        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Normalize whitespace
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text ?? '');
    }

    /**
     * Import all albums and their tracks into the database.
     *
     * @return array{albums_processed: int, albums_created: int, songs_created: int, artists_created: int, errors: array<int, string>}
     */
    public function importAllAlbums(bool $dryRun = false, ?callable $progressCallback = null): array
    {
        $stats = [
            'albums_processed' => 0,
            'albums_created' => 0,
            'songs_created' => 0,
            'artists_created' => 0,
            'errors' => [],
        ];

        $albums = $this->getAlbumList();

        if ($albums->isEmpty()) {
            $stats['errors'][] = 'Failed to fetch album list from Fandom';

            return $stats;
        }

        foreach ($albums as $albumData) {
            $stats['albums_processed']++;

            if ($progressCallback) {
                $progressCallback("Processing: {$albumData['name']}");
            }

            if ($dryRun) {
                if ($progressCallback) {
                    $progressCallback("  [DRY RUN] Would create album #{$albumData['number']}");
                }

                continue;
            }

            try {
                // Create or update album
                $album = Album::updateOrCreate(
                    [
                        'number' => $albumData['number'],
                        'type' => $albumData['type'],
                    ],
                    [
                        'name' => $albumData['name'],
                        'release_date' => $albumData['release_date'],
                    ]
                );

                if ($album->wasRecentlyCreated) {
                    $stats['albums_created']++;
                }

                // Scrape and import tracks if we have a Fandom URL
                if ($albumData['fandom_url']) {
                    $tracks = $this->scrapeTrackListing($albumData['fandom_url']);

                    if ($progressCallback) {
                        $progressCallback('  Found '.count($tracks).' tracks');
                    }

                    foreach ($tracks as $trackData) {
                        // Find or create artist
                        $artist = Artist::firstOrCreate(
                            ['name' => $trackData['artist']]
                        );

                        if ($artist->wasRecentlyCreated) {
                            $stats['artists_created']++;
                        }

                        // Find or create song
                        $song = Song::firstOrCreate(
                            ['title' => $trackData['title']]
                        );

                        if ($song->wasRecentlyCreated) {
                            $stats['songs_created']++;
                        }

                        // Attach song to album if not already attached
                        if (! $album->songs()->where('song_id', $song->id)->exists()) {
                            $album->songs()->attach($song->id, [
                                'track_number' => $trackData['track_number'],
                                'chart_position' => $trackData['chart_position'],
                            ]);
                        }

                        // Attach artist to song if not already attached
                        if (! $song->artists()->where('artist_id', $artist->id)->exists()) {
                            $song->artists()->attach($artist->id, [
                                'is_primary' => true,
                            ]);
                        }
                    }

                    // Be nice to Fandom - add a small delay between requests
                    usleep(500000); // 0.5 seconds
                }
            } catch (\Exception $e) {
                $stats['errors'][] = "Album #{$albumData['number']}: ".$e->getMessage();
                Log::error('Failed to import album', [
                    'album' => $albumData,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }
}
