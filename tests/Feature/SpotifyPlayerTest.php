<?php

use App\Models\Album;
use App\Models\Artist;
use App\Models\Song;
use Livewire\Livewire;

beforeEach(function () {
    $album = Album::create([
        'number' => 50,
        'name' => "NOW That's What I Call Music! 50",
        'release_date' => '2001-11-19',
        'type' => 'regular',
    ]);

    $artist = Artist::create(['name' => 'Test Artist']);

    $song = Song::create([
        'title' => 'Test Song',
        'spotify_id' => 'spotify123',
    ]);

    $album->songs()->attach($song->id, [
        'track_number' => 1,
        'chart_position' => 5,
    ]);

    $song->artists()->attach($artist->id, ['is_primary' => true]);

    $this->song = $song;
    $this->album = $album;
    $this->artist = $artist;
});

test('spotify link is rendered for song with spotify id', function () {
    Livewire::test('music-lookup')
        ->set('selectedDate', '2001-11-19')
        ->call('search')
        ->assertSeeHtml('href="https://open.spotify.com/track/spotify123"')
        ->assertSeeHtml('target="_blank"');
});

test('spotify link is not rendered for song without spotify id', function () {
    $songWithoutSpotify = Song::create(['title' => 'No Spotify Song']);
    $this->album->songs()->attach($songWithoutSpotify->id, [
        'track_number' => 2,
        'chart_position' => 10,
    ]);
    $songWithoutSpotify->artists()->attach($this->artist->id, ['is_primary' => true]);

    Livewire::test('music-lookup')
        ->set('selectedDate', '2001-11-19')
        ->call('search')
        ->assertSeeHtml('href="https://open.spotify.com/track/spotify123"')
        ->assertDontSeeHtml('href="https://open.spotify.com/track/null"');
});

test('getSpotifyUrlForSong returns correct url for resolved song', function () {
    $component = Livewire::test('music-lookup')
        ->set('selectedDate', '2001-11-19')
        ->call('search');

    $url = $component->call('getSpotifyUrlForSong', $this->song->id)->get('resolvedSpotifyIds');

    expect($url[$this->song->id])->toBe('spotify123');
});

test('multiple songs with spotify ids show spotify links', function () {
    $song2 = Song::create([
        'title' => 'Second Song',
        'spotify_id' => 'spotify456',
    ]);
    $this->album->songs()->attach($song2->id, [
        'track_number' => 2,
        'chart_position' => 10,
    ]);
    $song2->artists()->attach($this->artist->id, ['is_primary' => true]);

    Livewire::test('music-lookup')
        ->set('selectedDate', '2001-11-19')
        ->call('search')
        ->assertSeeHtml('href="https://open.spotify.com/track/spotify123"')
        ->assertSeeHtml('href="https://open.spotify.com/track/spotify456"');
});

test('spotify link title indicates opening on spotify', function () {
    Livewire::test('music-lookup')
        ->set('selectedDate', '2001-11-19')
        ->call('search')
        ->assertSeeHtml('title="Open on Spotify"');
});
