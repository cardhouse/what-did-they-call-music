<?php

use App\Models\Album;
use App\Models\Artist;
use App\Models\Song;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;

use function Pest\Laravel\get;

beforeEach(function () {
    $album = Album::create([
        'number' => 50,
        'name' => "NOW That's What I Call Music! 50",
        'release_date' => '2001-11-19',
        'type' => 'regular',
    ]);

    $artist = Artist::create(['name' => 'Test Artist']);
    $song = Song::create(['title' => 'Test Song']);

    $album->songs()->attach($song->id, [
        'track_number' => 1,
        'chart_position' => 5,
    ]);

    $song->artists()->attach($artist->id, ['is_primary' => true]);
});

test('middleware logs page visit requests', function () {
    Log::shouldReceive('channel')
        ->with('requests')
        ->andReturnSelf();

    Log::shouldReceive('info')
        ->withArgs(function ($message, $context) {
            return $message === 'Request'
                && isset($context['ip'])
                && isset($context['method'])
                && isset($context['url'])
                && isset($context['path'])
                && isset($context['status'])
                && isset($context['duration_ms'])
                && isset($context['user_agent']);
        })
        ->once();

    get('/');
});

test('search logs date searched and album titles', function () {
    Log::shouldReceive('channel')
        ->with('requests')
        ->andReturnSelf();

    Log::shouldReceive('info')
        ->withArgs(function ($message, $context) {
            if ($message === 'Search completed') {
                return $context['date_searched'] === '2001-11-19'
                    && isset($context['albums_found'])
                    && isset($context['album_titles'])
                    && in_array("NOW That's What I Call Music! 50", $context['album_titles']);
            }
            return true;
        })
        ->atLeast()
        ->once();

    Livewire::test('music-lookup')
        ->set('selectedDate', '2001-11-19')
        ->call('search')
        ->assertSet('searched', true);
});

test('search logs when no albums are found', function () {
    Album::query()->delete();

    Album::create([
        'number' => 50,
        'name' => "NOW That's What I Call Music! 50",
        'release_date' => '2001-11-19',
        'type' => 'regular',
    ]);

    Log::shouldReceive('channel')
        ->with('requests')
        ->andReturnSelf();

    Log::shouldReceive('info')
        ->withArgs(function ($message, $context) {
            if ($message === 'Search returned no results') {
                return isset($context['ip'])
                    && isset($context['date_searched'])
                    && $context['albums_found'] === 0;
            }
            return true;
        })
        ->atLeast()
        ->once();

    Livewire::test('music-lookup')
        ->set('selectedDate', '2001-11-19')
        ->set('minAlbumReleaseDate', '1990-01-01')
        ->set('maxAlbumReleaseDate', '2025-01-01')
        ->call('search');
});

test('image proxy logs blocked domain access attempts', function () {
    Log::shouldReceive('channel')
        ->with('requests')
        ->andReturnSelf();

    Log::shouldReceive('warning')
        ->withArgs(function ($message, $context) {
            if ($message === 'Blocked image proxy request to disallowed domain') {
                return isset($context['ip'])
                    && isset($context['requested_url'])
                    && isset($context['user_agent']);
            }
            return true;
        })
        ->atLeast()
        ->once();

    Log::shouldReceive('info')->andReturnSelf();
    Log::shouldReceive('warning')->andReturnSelf();

    $maliciousUrl = base64_encode('https://evil-site.com/image.jpg');
    get("/img/{$maliciousUrl}")->assertStatus(403);
});

test('middleware logs client error requests as warnings', function () {
    Log::shouldReceive('channel')
        ->with('requests')
        ->andReturnSelf();

    Log::shouldReceive('warning')
        ->withArgs(function ($message, $context) {
            return $message === 'Client error request'
                && isset($context['status'])
                && $context['status'] >= 400
                && $context['status'] < 500;
        })
        ->once();

    get('/nonexistent-page-that-does-not-exist');
});
