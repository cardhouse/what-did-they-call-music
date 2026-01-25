<?php

return [
    'client_id' => env('SPOTIFY_CLIENT_ID', ''),
    'client_secret' => env('SPOTIFY_CLIENT_SECRET', ''),
    'accounts_base_url' => env('SPOTIFY_ACCOUNTS_BASE_URL', 'https://accounts.spotify.com'),
    'api_base_url' => env('SPOTIFY_API_BASE_URL', 'https://api.spotify.com'),

    /*
    |--------------------------------------------------------------------------
    | Artist Match Threshold
    |--------------------------------------------------------------------------
    |
    | The minimum similarity percentage (0-100) required for fuzzy artist
    | matching when an exact match is not found. Higher values are stricter.
    | Default is 80, meaning artists must be at least 80% similar.
    |
    */
    'artist_match_threshold' => (int) env('SPOTIFY_ARTIST_MATCH_THRESHOLD', 80),
];
