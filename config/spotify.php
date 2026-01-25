<?php

return [
    'client_id' => env('SPOTIFY_CLIENT_ID', ''),
    'client_secret' => env('SPOTIFY_CLIENT_SECRET', ''),
    'accounts_base_url' => env('SPOTIFY_ACCOUNTS_BASE_URL', 'https://accounts.spotify.com'),
    'api_base_url' => env('SPOTIFY_API_BASE_URL', 'https://api.spotify.com'),
];
