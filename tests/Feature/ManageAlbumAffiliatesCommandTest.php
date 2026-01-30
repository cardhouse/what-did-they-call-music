<?php

use App\Models\Album;

use function Pest\Laravel\artisan;

test('command lists all albums', function () {
    $album = Album::factory()->create([
        'number' => 12,
    ]);

    artisan('albums:affiliate --list')
        ->expectsOutputToContain($album->display_name)
        ->assertExitCode(0);
});

test('command lists albums missing affiliate links', function () {
    $missing = Album::factory()->create([
        'number' => 15,
        'affiliate_url' => null,
    ]);

    $withLink = Album::factory()->create([
        'number' => 16,
        'affiliate_url' => 'https://example.com/now-16',
    ]);

    artisan('albums:affiliate --missing')
        ->expectsOutputToContain($missing->display_name)
        ->doesntExpectOutputToContain($withLink->display_name)
        ->assertExitCode(0);
});

test('command sets an affiliate link by album number', function () {
    $album = Album::factory()->create([
        'number' => 21,
        'affiliate_url' => null,
    ]);

    artisan('albums:affiliate --set="https://example.com/now-21" --album=21')
        ->assertExitCode(0);

    expect($album->refresh()->affiliate_url)->toBe('https://example.com/now-21');
});

test('command clears an affiliate link by album number', function () {
    $album = Album::factory()->create([
        'number' => 22,
        'affiliate_url' => 'https://example.com/now-22',
    ]);

    artisan('albums:affiliate --clear --album=22')
        ->assertExitCode(0);

    expect($album->refresh()->affiliate_url)->toBeNull();
});

test('command sets an affiliate link by album id for specials', function () {
    $album = Album::factory()->create([
        'number' => null,
        'type' => 'special',
        'affiliate_url' => null,
    ]);

    artisan('albums:affiliate --set="https://example.com/special" --album-id='.$album->id)
        ->assertExitCode(0);

    expect($album->refresh()->affiliate_url)->toBe('https://example.com/special');
});
