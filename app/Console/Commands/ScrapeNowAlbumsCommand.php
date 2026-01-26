<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\FandomScraper;
use Illuminate\Console\Command;

class ScrapeNowAlbumsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'now:scrape 
                            {--dry-run : Show what would be scraped without saving}
                            {--limit= : Limit the number of albums to scrape}
                            {--start= : Start from a specific album number}';

    /**
     * The console command description.
     */
    protected $description = 'Scrape NOW album data from Fandom wiki';

    /**
     * Execute the console command.
     */
    public function handle(FandomScraper $scraper): int
    {
        $this->info('🎵 Starting NOW album scraping from Fandom wiki...');
        $this->newLine();

        $dryRun = $this->option('dry-run');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $start = $this->option('start') ? (int) $this->option('start') : null;

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No data will be saved');
            $this->newLine();
        }

        // Get album list
        $albums = $scraper->getAlbumList();

        // Apply filters
        if ($start) {
            $albums = $albums->filter(fn ($album) => $album['number'] >= $start);
        }

        if ($limit) {
            $albums = $albums->take($limit);
        }

        $this->info("📋 Found {$albums->count()} albums to process");
        $this->newLine();

        $progressBar = $this->output->createProgressBar($albums->count());
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');
        $progressBar->setMessage('Starting...');
        $progressBar->start();

        $stats = [
            'albums_processed' => 0,
            'albums_created' => 0,
            'songs_created' => 0,
            'artists_created' => 0,
            'errors' => [],
        ];

        foreach ($albums as $albumData) {
            $albumNumber = $albumData['number'];
            $progressBar->setMessage($albumNumber ? "NOW {$albumNumber}" : 'Unknown album');
            $progressBar->advance();

            if ($albumData['number'] === null || $albumData['release_date'] === null) {
                $stats['errors'][] = 'Album with missing number or release date was skipped.';

                continue;
            }

            /** @var array{number: int, name: string, release_date: string, type: string, fandom_url: string} $albumData */
            if ($dryRun) {
                $stats['albums_processed']++;

                continue;
            }

            try {
                $result = $this->processAlbum($scraper, $albumData);
                $stats['albums_processed']++;
                $stats['albums_created'] += $result['album_created'] ? 1 : 0;
                $stats['songs_created'] += $result['songs_created'];
                $stats['artists_created'] += $result['artists_created'];
            } catch (\Exception $e) {
                $stats['errors'][] = "Album #{$albumData['number']}: ".$e->getMessage();
            }

            // Be nice to Fandom
            usleep(500000); // 0.5 seconds
        }

        $progressBar->finish();
        $this->newLine(2);

        // Display results
        $this->displayResults($stats, $dryRun);

        return self::SUCCESS;
    }

    /**
     * Process a single album.
     *
     * @param  array{number: int, name: string, release_date: string, type: string, fandom_url: string}  $albumData
     * @return array{album_created: bool, songs_created: int, artists_created: int}
     */
    private function processAlbum(FandomScraper $scraper, array $albumData): array
    {
        $result = [
            'album_created' => false,
            'songs_created' => 0,
            'artists_created' => 0,
        ];

        // Create or update album
        $album = \App\Models\Album::updateOrCreate(
            [
                'number' => $albumData['number'],
                'type' => $albumData['type'],
            ],
            [
                'name' => $albumData['name'],
                'release_date' => $albumData['release_date'],
            ]
        );

        $result['album_created'] = $album->wasRecentlyCreated;

        // Scrape tracks if we have a Fandom URL
        if ($albumData['fandom_url']) {
            $album->songs()->detach();
            $tracks = $scraper->scrapeTrackListing($albumData['fandom_url']);

            foreach ($tracks as $trackData) {
                // Find or create artist
                $artist = \App\Models\Artist::firstOrCreate(
                    ['name' => $trackData['artist']]
                );

                if ($artist->wasRecentlyCreated) {
                    $result['artists_created']++;
                }

                // Find or create song
                $song = \App\Models\Song::firstOrCreate(
                    ['title' => $trackData['title']]
                );

                if ($song->wasRecentlyCreated) {
                    $result['songs_created']++;
                }

                // Attach song to album if not already attached
                if (! $album->songs()->where('song_id', $song->id)->exists()) {
                    $album->songs()->attach($song->id, [
                        'track_number' => $trackData['track_number'],
                    ]);
                }

                // Attach artist to song if not already attached
                if (! $song->artists()->where('artist_id', $artist->id)->exists()) {
                    $song->artists()->attach($artist->id, [
                        'is_primary' => true,
                    ]);
                }
            }
        }

        return $result;
    }

    /**
     * Display the results of the scraping operation.
     *
     * @param  array{albums_processed: int, albums_created: int, songs_created: int, artists_created: int, errors: array<string>}  $stats
     */
    private function displayResults(array $stats, bool $dryRun): void
    {
        $this->info('📊 Scraping Results:');
        $this->newLine();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Albums Processed', $stats['albums_processed']],
                ['Albums Created', $dryRun ? 'N/A (dry run)' : $stats['albums_created']],
                ['Songs Created', $dryRun ? 'N/A (dry run)' : $stats['songs_created']],
                ['Artists Created', $dryRun ? 'N/A (dry run)' : $stats['artists_created']],
                ['Errors', count($stats['errors'])],
            ]
        );

        if (! empty($stats['errors'])) {
            $this->newLine();
            $this->error('⚠️ Errors encountered:');
            foreach ($stats['errors'] as $error) {
                $this->line("  - {$error}");
            }
        }

        $this->newLine();

        if ($dryRun) {
            $this->info('💡 Run without --dry-run to actually import the data.');
        } else {
            $this->info('✅ Scraping complete!');
        }
    }
}
