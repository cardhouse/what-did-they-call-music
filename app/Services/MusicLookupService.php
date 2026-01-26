<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Album;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class MusicLookupService
{
    /**
     * Find the most recent NOW album(s) released as of the given date.
     * If both regular and special editions exist for the same date, return both.
     *
     * @return Collection<int, Album>
     */
    public function findAlbumsForDate(Carbon $date): Collection
    {
        // Get the most recent release date for albums released by the given date
        $mostRecentDate = Album::releasedByDate($date)
            ->max('release_date');

        if (! $mostRecentDate) {
            return new Collection;
        }

        // Return all albums released on that most recent date
        return Album::with(['songs.artists', 'songs.primaryArtists'])
            ->where('release_date', $mostRecentDate)
            ->orderBy('type')
            ->orderBy('number')
            ->get();
    }

    /**
     * Check if a date is before the first NOW album release.
     */
    public function isBeforeFirstAlbum(Carbon $date): bool
    {
        $firstAlbumDate = Album::min('release_date');

        return ! $firstAlbumDate || $date->lt(Carbon::parse($firstAlbumDate));
    }

    /**
     * Get the date of the first NOW album release.
     */
    public function getFirstAlbumDate(): ?Carbon
    {
        $firstDate = Album::min('release_date');

        return $firstDate ? Carbon::parse($firstDate) : null;
    }

    /**
     * Get statistics about the NOW album collection.
     *
     * @return array{total_albums: int, total_songs: int, date_range: array{first: ?Carbon, latest: ?Carbon}, album_types: array<string, int>}
     */
    public function getCollectionStats(): array
    {
        // Single query for album aggregates instead of multiple queries
        $albumStats = Album::selectRaw('
            COUNT(*) as total,
            MIN(release_date) as first_date,
            MAX(release_date) as latest_date
        ')->first();

        return [
            'total_albums' => $albumStats->total ?? 0,
            'total_songs' => \App\Models\Song::count(),
            'date_range' => [
                'first' => $albumStats->first_date ? Carbon::parse($albumStats->first_date) : null,
                'latest' => $albumStats->latest_date ? Carbon::parse($albumStats->latest_date) : null,
            ],
            'album_types' => Album::selectRaw('type, COUNT(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type')
                ->toArray(),
        ];
    }

    /**
     * Get a random valid release date from the database.
     */
    public function getRandomValidDate(): ?Carbon
    {
        $randomAlbum = Album::inRandomOrder()->first();

        return $randomAlbum ? Carbon::parse($randomAlbum->release_date) : null;
    }

    /**
     * @param  Collection<int, Album>  $albums
     */
    public function enrichSpotifyIds(Collection $albums): void
    {
        if ($albums->isEmpty()) {
            return;
        }

        $matcher = app(SpotifyMatchService::class);

        $albums
            ->flatMap(fn (Album $album) => $album->songs)
            ->unique('id')
            ->filter(fn ($song) => ! $song->spotify_id)
            ->each(function ($song) use ($matcher): void {
                $primaryArtist = $song->primaryArtists->first();

                if (! $primaryArtist) {
                    return;
                }

                $trackId = $matcher->findTrackId($song->title, $primaryArtist->name);

                if (! $trackId) {
                    return;
                }

                $song->spotify_id = $trackId;
                $song->save();
            });
    }
}
