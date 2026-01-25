<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Album;
use App\Models\Artist;
use App\Models\Song;
use Carbon\Carbon;

class NowAlbumsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Sample NOW albums with some real data for testing
        $albums = [
            [
                'number' => 45,
                'name' => "NOW That's What I Call Music! 45",
                'release_date' => '2000-03-27',
                'type' => 'regular',
                'songs' => [
                    ['title' => 'American Pie', 'artist' => 'Madonna', 'track' => 1, 'chart' => 1],
                    ['title' => 'Pure Shores', 'artist' => 'All Saints', 'track' => 2, 'chart' => 2],
                    ['title' => 'Born to Make You Happy', 'artist' => 'Britney Spears', 'track' => 3, 'chart' => 17],
                    ['title' => 'Rise', 'artist' => 'Gabrielle', 'track' => 4, 'chart' => 1],
                    ['title' => 'Go Let It Out', 'artist' => 'Oasis', 'track' => 5, 'chart' => 1],
                ]
            ],
            [
                'number' => 46,
                'name' => "NOW That's What I Call Music! 46",
                'release_date' => '2000-07-24',
                'type' => 'regular',
                'songs' => [
                    ['title' => 'It\'s Gonna Be Me', 'artist' => '*NSYNC', 'track' => 1, 'chart' => 9],
                    ['title' => 'Spinning Around', 'artist' => 'Kylie Minogue', 'track' => 2, 'chart' => 1],
                    ['title' => 'Breathe', 'artist' => 'Faith Hill', 'track' => 3, 'chart' => 25],
                    ['title' => 'Try Again', 'artist' => 'Aaliyah', 'track' => 4, 'chart' => 5],
                    ['title' => 'Yellow', 'artist' => 'Coldplay', 'track' => 5, 'chart' => 4],
                ]
            ],
            [
                'number' => null,
                'name' => "NOW That's What I Call Music! Christmas 2000",
                'release_date' => '2000-11-20',
                'type' => 'christmas',
                'songs' => [
                    ['title' => 'All I Want for Christmas Is You', 'artist' => 'Mariah Carey', 'track' => 1, 'chart' => null],
                    ['title' => 'Last Christmas', 'artist' => 'Wham!', 'track' => 2, 'chart' => null],
                    ['title' => 'Wonderful Christmastime', 'artist' => 'Paul McCartney', 'track' => 3, 'chart' => null],
                    ['title' => 'Blue Christmas', 'artist' => 'Elvis Presley', 'track' => 4, 'chart' => null],
                ]
            ],
            [
                'number' => 47,
                'name' => "NOW That's What I Call Music! 47",
                'release_date' => '2000-11-27',
                'type' => 'regular',
                'songs' => [
                    ['title' => 'Music', 'artist' => 'Madonna', 'track' => 1, 'chart' => 1],
                    ['title' => 'Against All Odds', 'artist' => 'Mariah Carey feat. Westlife', 'track' => 2, 'chart' => 1],
                    ['title' => 'Beautiful Day', 'artist' => 'U2', 'track' => 3, 'chart' => 1],
                    ['title' => 'Holler', 'artist' => 'Spice Girls', 'track' => 4, 'chart' => 1],
                    ['title' => 'Come on Over Baby', 'artist' => 'Christina Aguilera', 'track' => 5, 'chart' => 8],
                ]
            ]
        ];

        foreach ($albums as $albumData) {
            $album = Album::create([
                'number' => $albumData['number'],
                'name' => $albumData['name'],
                'release_date' => Carbon::parse($albumData['release_date']),
                'type' => $albumData['type'],
            ]);

            foreach ($albumData['songs'] as $songData) {
                // Create or find artist
                $artist = Artist::firstOrCreate(['name' => $songData['artist']]);

                // Create or find song
                $song = Song::firstOrCreate(['title' => $songData['title']]);

                // Attach song to album with track info
                $album->songs()->attach($song->id, [
                    'track_number' => $songData['track'],
                    'chart_position' => $songData['chart'],
                ]);

                // Attach artist to song as primary artist
                $song->artists()->syncWithoutDetaching([$artist->id => ['is_primary' => true]]);
            }
        }
    }
}
