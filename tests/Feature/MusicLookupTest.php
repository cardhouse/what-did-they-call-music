<?php

use App\Models\Album;
use App\Models\Artist;
use App\Models\Song;
use App\Services\MusicLookupService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use function Pest\Laravel\get;

beforeEach(function () {
    // Create test data
    $album = Album::create([
        'number' => 50,
        'name' => "NOW That's What I Call Music! 50",
        'release_date' => '2001-11-19',
        'type' => 'regular',
    ]);

    $artist = Artist::create(['name' => 'Test Artist']);
    $song = Song::create(['title' => 'Test Song']);
    $this->song = $song;

    $album->songs()->attach($song->id, [
        'track_number' => 1,
        'chart_position' => 5,
    ]);

    $song->artists()->attach($artist->id, ['is_primary' => true]);
});

test('homepage loads successfully', function () {
    $response = get('/');
    $response->assertSuccessful();
});

test('music lookup service finds albums for date', function () {
    $service = new MusicLookupService();
    $albums = $service->findAlbumsForDate(Carbon::parse('2001-12-01'));
    
    expect($albums)->toHaveCount(1);
    expect($albums->first()->name)->toBe("NOW That's What I Call Music! 50");
});

test('music lookup service handles dates before first album', function () {
    $service = new MusicLookupService();
    
    expect($service->isBeforeFirstAlbum(Carbon::parse('1980-01-01')))->toBeTrue();
    expect($service->isBeforeFirstAlbum(Carbon::parse('2002-01-01')))->toBeFalse();
});

test('livewire component can search for albums', function () {
    Livewire::test('music-lookup')
        ->set('selectedDate', '2001-11-19')
        ->call('search')
        ->assertSet('searched', true)
        ->assertSet('errorMessage', '')
        ->assertSee("NOW That's What I Call Music! 50")
        ->assertSee('Test Song')
        ->assertSee('Test Artist');
});

test('spotify lookup populates song id and listen link', function () {
    config()->set('spotify.client_id', 'client-id');
    config()->set('spotify.client_secret', 'client-secret');

    Http::fake([
        'https://accounts.spotify.com/api/token' => Http::response([
            'access_token' => 'test-token',
            'expires_in' => 3600,
        ]),
        'https://api.spotify.com/v1/search*' => Http::response([
            'tracks' => [
                'items' => [
                    [
                        'id' => 'spotify123',
                        'name' => 'Test Song',
                        'artists' => [
                            ['name' => 'Test Artist'],
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    Livewire::test('music-lookup')
        ->set('selectedDate', '2001-11-19')
        ->call('search')
        ->call('resolveSpotifyForSong', $this->song->id)
        ->assertHasNoErrors();

    expect(Song::first()->refresh()->spotify_id)->toBe('spotify123');
});

test('spotify lookup skips non-matching results', function () {
    config()->set('spotify.client_id', 'client-id');
    config()->set('spotify.client_secret', 'client-secret');

    Http::fake([
        'https://accounts.spotify.com/api/token' => Http::response([
            'access_token' => 'test-token',
            'expires_in' => 3600,
        ]),
        'https://api.spotify.com/v1/search*' => Http::response([
            'tracks' => [
                'items' => [
                    [
                        'id' => 'spotify999',
                        'name' => 'Different Song',
                        'artists' => [
                            ['name' => 'Other Artist'],
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    Livewire::test('music-lookup')
        ->set('selectedDate', '2001-11-19')
        ->call('search')
        ->call('resolveSpotifyForSong', $this->song->id)
        ->assertHasNoErrors();

    expect(Song::first()->refresh()->spotify_id)->toBeNull();
});

test('livewire component shows error for early dates', function () {
    Livewire::test('music-lookup')
        ->set('selectedDate', '1980-01-01')
        ->call('search')
        ->assertHasErrors(['selectedDate'])
        ->assertSee('Pick a date on or after November 2001.');
});

test('livewire component validates date input', function () {
    Livewire::test('music-lookup')
        ->set('selectedDate', 'invalid-date')
        ->call('search')
        ->assertHasErrors(['selectedDate']);
});

test('livewire component enforces minimum album release date', function () {
    Livewire::test('music-lookup')
        ->set('selectedDate', '2001-11-18')
        ->call('search')
        ->assertHasErrors(['selectedDate']);
});

test('livewire component enforces maximum album release date', function () {
    Livewire::test('music-lookup')
        ->set('selectedDate', '2001-11-20')
        ->call('search')
        ->assertHasErrors(['selectedDate']);
});

test('pickEra clamps dates before minimum to minimum date', function () {
    // Test data has album from 2001-11-19, so picking 80s (1983) or 90s (1995) should clamp to 2001-11-19
    Livewire::test('music-lookup')
        ->call('pickEra', '1983')
        ->assertSet('selectedDate', '2001-11-19')
        ->assertSet('searched', true)
        ->assertHasNoErrors();
});

test('pickEra clamps dates after maximum to maximum date', function () {
    // Test data has album from 2001-11-19, so picking 2015 or 2005 should clamp to 2001-11-19
    Livewire::test('music-lookup')
        ->call('pickEra', '2015')
        ->assertSet('selectedDate', '2001-11-19')
        ->assertSet('searched', true)
        ->assertHasNoErrors();
});

test('pickEra with 90s clamps to minimum date when outside range', function () {
    Livewire::test('music-lookup')
        ->call('pickEra', '1995')
        ->assertSet('selectedDate', '2001-11-19')
        ->assertSet('searched', true)
        ->assertHasNoErrors();
});

test('pickEra with Y2K clamps to maximum date when outside range', function () {
    Livewire::test('music-lookup')
        ->call('pickEra', '2005')
        ->assertSet('selectedDate', '2001-11-19')
        ->assertSet('searched', true)
        ->assertHasNoErrors();
});

test('isEraRelevant returns true for decade containing data', function () {
    // Album is from 2001, so 2000s decade should be relevant
    $component = Livewire::test('music-lookup');
    
    // Access the component instance to call the method directly
    $instance = $component->instance();
    
    // 2000s should be relevant (2001 is in the 2000s)
    expect($instance->isEraRelevant('2005'))->toBeTrue();
    expect($instance->isEraRelevant('2001'))->toBeTrue();
});

test('isEraRelevant returns false for decades without data', function () {
    // Album is from 2001, so 80s and 90s should not be relevant
    $component = Livewire::test('music-lookup');
    $instance = $component->instance();
    
    expect($instance->isEraRelevant('1983'))->toBeFalse();
    expect($instance->isEraRelevant('1995'))->toBeFalse();
});

test('isEraRelevant returns false for future decades without data', function () {
    // Album is from 2001, so 2010s should not be relevant
    $component = Livewire::test('music-lookup');
    $instance = $component->instance();
    
    expect($instance->isEraRelevant('2015'))->toBeFalse();
});

test('pickEra performs search after setting date', function () {
    Livewire::test('music-lookup')
        ->call('pickEra', '2001')
        ->assertSet('searched', true)
        ->assertSee("NOW That's What I Call Music! 50");
});

test('isEraRelevant works with date range spanning multiple decades', function () {
    // Add an album from the 90s to span multiple decades
    Album::create([
        'number' => 40,
        'name' => "NOW That's What I Call Music! 40",
        'release_date' => '1998-07-20',
        'type' => 'regular',
    ]);

    $component = Livewire::test('music-lookup');
    $instance = $component->instance();

    // Now data spans 1998-2001, so 90s and 2000s should be relevant
    expect($instance->isEraRelevant('1995'))->toBeTrue();
    expect($instance->isEraRelevant('2005'))->toBeTrue();
    // 80s should still be irrelevant
    expect($instance->isEraRelevant('1983'))->toBeFalse();
    // 2010s should still be irrelevant
    expect($instance->isEraRelevant('2015'))->toBeFalse();
});

test('pickEra with date within range does not clamp', function () {
    // Add an album from the 90s to have a wider range
    Album::create([
        'number' => 40,
        'name' => "NOW That's What I Call Music! 40",
        'release_date' => '1998-07-20',
        'type' => 'regular',
    ]);

    // Now range is 1998-07-20 to 2001-11-19
    // Picking year 2000 should give 2000-01-01 (within range)
    Livewire::test('music-lookup')
        ->call('pickEra', '2000')
        ->assertSet('selectedDate', '2000-01-01')
        ->assertSet('searched', true)
        ->assertHasNoErrors();
});

test('surpriseMe picks a date within valid range', function () {
    $component = Livewire::test('music-lookup');
    $component->call('surpriseMe');
    
    // After surpriseMe, the selectedDate should be within the valid range
    $selectedDate = $component->get('selectedDate');
    $minDate = $component->get('minAlbumReleaseDate');
    $maxDate = $component->get('maxAlbumReleaseDate');
    
    expect($selectedDate)->not->toBeNull();
    expect(Carbon::parse($selectedDate)->gte(Carbon::parse($minDate)))->toBeTrue();
    expect(Carbon::parse($selectedDate)->lte(Carbon::parse($maxDate)))->toBeTrue();
});
