<?php

use App\Services\SpotifyClient;
use App\Services\SpotifyMatchService;

beforeEach(function () {
    $this->client = Mockery::mock(SpotifyClient::class);
    $this->service = new SpotifyMatchService($this->client);
});

afterEach(function () {
    Mockery::close();
});

describe('findTrackId', function () {
    test('returns null for empty title', function () {
        $this->client->shouldNotReceive('searchTracks');
        $this->client->shouldNotReceive('searchTracksByTitle');

        expect($this->service->findTrackId('', 'Artist'))->toBeNull();
        expect($this->service->findTrackId('   ', 'Artist'))->toBeNull();
    });

    test('returns null for empty artist', function () {
        $this->client->shouldNotReceive('searchTracks');
        $this->client->shouldNotReceive('searchTracksByTitle');

        expect($this->service->findTrackId('Title', ''))->toBeNull();
        expect($this->service->findTrackId('Title', '   '))->toBeNull();
    });

    test('finds track with exact match', function () {
        $this->client->shouldReceive('searchTracks')
            ->with('Hello', 'Adele')
            ->once()
            ->andReturn([
                [
                    'id' => 'spotify123',
                    'name' => 'Hello',
                    'artists' => [['name' => 'Adele']],
                ],
            ]);

        expect($this->service->findTrackId('Hello', 'Adele'))->toBe('spotify123');
    });

    test('uses fallback search when strict search returns no results', function () {
        $this->client->shouldReceive('searchTracks')
            ->with('Hello', 'Adele')
            ->once()
            ->andReturn([]);

        $this->client->shouldReceive('searchTracksByTitle')
            ->with('Hello')
            ->once()
            ->andReturn([
                [
                    'id' => 'spotify456',
                    'name' => 'Hello',
                    'artists' => [['name' => 'Adele']],
                ],
            ]);

        expect($this->service->findTrackId('Hello', 'Adele'))->toBe('spotify456');
    });

    test('uses fallback search when strict search has no matching tracks', function () {
        $this->client->shouldReceive('searchTracks')
            ->with('Hello', 'Adele')
            ->once()
            ->andReturn([
                [
                    'id' => 'wrong123',
                    'name' => 'Different Song',
                    'artists' => [['name' => 'Adele']],
                ],
            ]);

        $this->client->shouldReceive('searchTracksByTitle')
            ->with('Hello')
            ->once()
            ->andReturn([
                [
                    'id' => 'spotify456',
                    'name' => 'Hello',
                    'artists' => [['name' => 'Adele']],
                ],
            ]);

        expect($this->service->findTrackId('Hello', 'Adele'))->toBe('spotify456');
    });
});

describe('artist matching - exact match', function () {
    test('matches identical artist names', function () {
        $this->client->shouldReceive('searchTracks')
            ->andReturn([
                [
                    'id' => 'spotify123',
                    'name' => 'Test Song',
                    'artists' => [['name' => 'Test Artist']],
                ],
            ]);

        expect($this->service->findTrackId('Test Song', 'Test Artist'))->toBe('spotify123');
    });

    test('matches artist names case-insensitively', function () {
        $this->client->shouldReceive('searchTracks')
            ->andReturn([
                [
                    'id' => 'spotify123',
                    'name' => 'Test Song',
                    'artists' => [['name' => 'TEST ARTIST']],
                ],
            ]);

        expect($this->service->findTrackId('Test Song', 'test artist'))->toBe('spotify123');
    });
});

describe('artist matching - the prefix', function () {
    test('matches Beatles to The Beatles', function () {
        $this->client->shouldReceive('searchTracks')
            ->andReturn([
                [
                    'id' => 'spotify123',
                    'name' => 'Hey Jude',
                    'artists' => [['name' => 'The Beatles']],
                ],
            ]);

        expect($this->service->findTrackId('Hey Jude', 'Beatles'))->toBe('spotify123');
    });

    test('matches The Beatles to Beatles', function () {
        $this->client->shouldReceive('searchTracks')
            ->andReturn([
                [
                    'id' => 'spotify123',
                    'name' => 'Hey Jude',
                    'artists' => [['name' => 'Beatles']],
                ],
            ]);

        expect($this->service->findTrackId('Hey Jude', 'The Beatles'))->toBe('spotify123');
    });
});

describe('artist matching - contains match', function () {
    test('matches partial artist name when contained', function () {
        $this->client->shouldReceive('searchTracks')
            ->andReturn([
                [
                    'id' => 'spotify123',
                    'name' => 'Test Song',
                    'artists' => [['name' => 'Paul McCartney']],
                ],
            ]);

        expect($this->service->findTrackId('Test Song', 'McCartney'))->toBe('spotify123');
    });

    test('does not match very short artist names', function () {
        $this->client->shouldReceive('searchTracks')->andReturn([]);
        $this->client->shouldReceive('searchTracksByTitle')
            ->andReturn([
                [
                    'id' => 'spotify123',
                    'name' => 'Test Song',
                    'artists' => [['name' => 'Pink Floyd']],
                ],
            ]);

        // "Pi" is too short (< 3 chars) to trigger contains match
        expect($this->service->findTrackId('Test Song', 'Pi'))->toBeNull();
    });

    test('does not match when length difference is too large', function () {
        $this->client->shouldReceive('searchTracks')->andReturn([]);
        $this->client->shouldReceive('searchTracksByTitle')
            ->andReturn([
                [
                    'id' => 'spotify123',
                    'name' => 'Test Song',
                    'artists' => [['name' => 'Pink Floyd and The Very Long Band Name']],
                ],
            ]);

        // "Pink" is contained but length difference is > 50%
        expect($this->service->findTrackId('Test Song', 'Pink'))->toBeNull();
    });
});

describe('artist matching - similarity match', function () {
    test('matches similar artist names above threshold', function () {
        config()->set('spotify.artist_match_threshold', 80);

        $this->client->shouldReceive('searchTracks')
            ->andReturn([
                [
                    'id' => 'spotify123',
                    'name' => 'Test Song',
                    'artists' => [['name' => 'Beyonce']],  // Without accent
                ],
            ]);

        // "Beyoncé" normalized becomes "beyonce" which matches exactly
        expect($this->service->findTrackId('Test Song', 'Beyoncé'))->toBe('spotify123');
    });

    test('does not match dissimilar artist names', function () {
        config()->set('spotify.artist_match_threshold', 80);

        $this->client->shouldReceive('searchTracks')->andReturn([]);
        $this->client->shouldReceive('searchTracksByTitle')
            ->andReturn([
                [
                    'id' => 'spotify123',
                    'name' => 'Test Song',
                    'artists' => [['name' => 'Lady Gaga']],
                ],
            ]);

        expect($this->service->findTrackId('Test Song', 'Madonna'))->toBeNull();
    });

    test('respects configurable threshold', function () {
        // With a very high threshold, similar names won't match
        config()->set('spotify.artist_match_threshold', 99);

        $this->client->shouldReceive('searchTracks')->andReturn([]);
        $this->client->shouldReceive('searchTracksByTitle')
            ->andReturn([
                [
                    'id' => 'spotify123',
                    'name' => 'Test Song',
                    'artists' => [['name' => 'Johnn Smith']],  // Typo
                ],
            ]);

        expect($this->service->findTrackId('Test Song', 'John Smith'))->toBeNull();
    });

    test('matches with lower threshold', function () {
        // With a lower threshold, similar names will match
        config()->set('spotify.artist_match_threshold', 70);

        $this->client->shouldReceive('searchTracks')->andReturn([]);
        $this->client->shouldReceive('searchTracksByTitle')
            ->andReturn([
                [
                    'id' => 'spotify123',
                    'name' => 'Test Song',
                    'artists' => [['name' => 'Johnn Smith']],  // Typo
                ],
            ]);

        expect($this->service->findTrackId('Test Song', 'John Smith'))->toBe('spotify123');
    });
});

describe('title matching - strict', function () {
    test('requires exact title match after normalization', function () {
        $this->client->shouldReceive('searchTracks')->andReturn([]);
        $this->client->shouldReceive('searchTracksByTitle')
            ->andReturn([
                [
                    'id' => 'spotify123',
                    'name' => 'Different Song',
                    'artists' => [['name' => 'Test Artist']],
                ],
            ]);

        expect($this->service->findTrackId('Test Song', 'Test Artist'))->toBeNull();
    });

    test('normalizes title for comparison', function () {
        $this->client->shouldReceive('searchTracks')
            ->andReturn([
                [
                    'id' => 'spotify123',
                    'name' => 'Hello (Radio Edit)',
                    'artists' => [['name' => 'Adele']],
                ],
            ]);

        // Parentheses content is stripped during normalization
        expect($this->service->findTrackId('Hello', 'Adele'))->toBe('spotify123');
    });

    test('strips featuring from title', function () {
        $this->client->shouldReceive('searchTracks')
            ->andReturn([
                [
                    'id' => 'spotify123',
                    'name' => 'Song Title',
                    'artists' => [['name' => 'Main Artist']],
                ],
            ]);

        expect($this->service->findTrackId('Song Title feat. Other Artist', 'Main Artist'))->toBe('spotify123');
    });
});

describe('multiple artists', function () {
    test('matches when artist is one of multiple', function () {
        $this->client->shouldReceive('searchTracks')
            ->andReturn([
                [
                    'id' => 'spotify123',
                    'name' => 'Collaboration Song',
                    'artists' => [
                        ['name' => 'Artist One'],
                        ['name' => 'Artist Two'],
                        ['name' => 'Artist Three'],
                    ],
                ],
            ]);

        expect($this->service->findTrackId('Collaboration Song', 'Artist Two'))->toBe('spotify123');
    });
});

describe('edge cases', function () {
    test('handles special characters in artist names', function () {
        // Strict search returns the track, but artist won't match exactly
        // because "P!nk" normalizes to "p nk" and "Pink" normalizes to "pink"
        $this->client->shouldReceive('searchTracks')
            ->andReturn([
                [
                    'id' => 'spotify123',
                    'name' => 'Test Song',
                    'artists' => [['name' => 'P!nk']],
                ],
            ]);

        // Fallback search also returns the track
        $this->client->shouldReceive('searchTracksByTitle')
            ->andReturn([
                [
                    'id' => 'spotify123',
                    'name' => 'Test Song',
                    'artists' => [['name' => 'P!nk']],
                ],
            ]);

        // "p nk" and "pink" should match via similarity (75%+ similar)
        expect($this->service->findTrackId('Test Song', 'Pink'))->toBe('spotify123');
    });

    test('handles ampersand vs and', function () {
        $this->client->shouldReceive('searchTracks')
            ->andReturn([
                [
                    'id' => 'spotify123',
                    'name' => 'Test Song',
                    'artists' => [['name' => 'Tom and Jerry']],
                ],
            ]);

        expect($this->service->findTrackId('Test Song', 'Tom & Jerry'))->toBe('spotify123');
    });

    test('returns null when no tracks returned', function () {
        $this->client->shouldReceive('searchTracks')->andReturn([]);
        $this->client->shouldReceive('searchTracksByTitle')->andReturn([]);

        expect($this->service->findTrackId('Unknown Song', 'Unknown Artist'))->toBeNull();
    });

    test('handles malformed track data gracefully', function () {
        $this->client->shouldReceive('searchTracks')
            ->andReturn([
                'not an array',
                ['name' => 'Missing artists'],
                ['artists' => [['name' => 'Missing name']]],
                null,
            ]);
        $this->client->shouldReceive('searchTracksByTitle')->andReturn([]);

        expect($this->service->findTrackId('Test Song', 'Test Artist'))->toBeNull();
    });
});
