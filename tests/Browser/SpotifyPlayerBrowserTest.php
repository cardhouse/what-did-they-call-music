<?php

use App\Models\Album;
use App\Models\Artist;
use App\Models\Song;

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
        'spotify_id' => '4PTG3Z6ehGkBFwjybzWkR8',
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

test('spotify link is visible for songs with spotify id', function () {
    $page = visit('/');

    $page->assertSee('What Did They Call Music')
        ->click('Search')
        ->wait(3)
        ->assertSee('Test Song')
        ->assertSee('Test Artist')
        ->assertVisible('[title="Open on Spotify"]')
        ->assertNoJavaScriptErrors();
});

test('spotify link points to correct spotify url', function () {
    $page = visit('/');

    $page->click('Search')
        ->wait(3);

    $href = $page->attribute('[title="Open on Spotify"]', 'href');

    expect($href)->toContain('open.spotify.com/track/4PTG3Z6ehGkBFwjybzWkR8');
});

test('spotify link opens in new tab', function () {
    $page = visit('/');

    $page->click('Search')
        ->wait(3);

    $target = $page->attribute('[title="Open on Spotify"]', 'target');

    expect($target)->toBe('_blank');
});

test('multiple songs with spotify ids show spotify links', function () {
    $song2 = Song::create([
        'title' => 'Second Song',
        'spotify_id' => '3n3Ppam7vgaVa1iaRUc9Lp',
    ]);

    $this->album->songs()->attach($song2->id, [
        'track_number' => 2,
        'chart_position' => 10,
    ]);

    $song2->artists()->attach($this->artist->id, ['is_primary' => true]);

    $page = visit('/');

    $page->click('Search')
        ->wait(3)
        ->assertSee('Test Song')
        ->assertSee('Second Song')
        ->assertNoJavaScriptErrors();
});

test('page loads without javascript errors', function () {
    $page = visit('/');

    $page->click('Search')
        ->wait(3)
        ->assertNoJavaScriptErrors();
});
