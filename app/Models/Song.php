<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Song extends Model
{
    protected $fillable = [
        'title',
        'original_release_year',
        'spotify_id',
        'spotify_lookup_at',
        'apple_music_id',
    ];

    protected function casts(): array
    {
        return [
            'original_release_year' => 'integer',
            'spotify_lookup_at' => 'datetime',
        ];
    }

    public function albums(): BelongsToMany
    {
        return $this->belongsToMany(Album::class)
            ->withPivot(['track_number', 'chart_position'])
            ->withTimestamps();
    }

    public function artists(): BelongsToMany
    {
        return $this->belongsToMany(Artist::class)
            ->withPivot(['is_primary'])
            ->withTimestamps();
    }

    public function primaryArtists(): BelongsToMany
    {
        return $this->artists()->wherePivot('is_primary', true);
    }

    public function featuredArtists(): BelongsToMany
    {
        return $this->artists()->wherePivot('is_primary', false);
    }

    public function getSpotifyUrlAttribute(): ?string
    {
        return $this->spotify_id ? "https://open.spotify.com/track/{$this->spotify_id}" : null;
    }

    public function getAppleMusicUrlAttribute(): ?string
    {
        return $this->apple_music_id ? "https://music.apple.com/us/song/{$this->apple_music_id}" : null;
    }

    public function getArtistNamesAttribute(): string
    {
        $primary = $this->primaryArtists->pluck('name')->join(', ');
        $featured = $this->featuredArtists->pluck('name')->join(', ');

        if ($featured) {
            return "{$primary} feat. {$featured}";
        }

        return $primary;
    }
}
