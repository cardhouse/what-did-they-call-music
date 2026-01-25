<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Artist extends Model
{
    protected $fillable = [
        'name',
        'spotify_id',
        'apple_music_id',
    ];

    public function songs(): BelongsToMany
    {
        return $this->belongsToMany(Song::class)
            ->withPivot(['is_primary'])
            ->withTimestamps();
    }

    public function primarySongs(): BelongsToMany
    {
        return $this->songs()->wherePivot('is_primary', true);
    }

    public function featuredSongs(): BelongsToMany
    {
        return $this->songs()->wherePivot('is_primary', false);
    }

    public function getSpotifyUrlAttribute(): ?string
    {
        return $this->spotify_id ? "https://open.spotify.com/artist/{$this->spotify_id}" : null;
    }

    public function getAppleMusicUrlAttribute(): ?string
    {
        return $this->apple_music_id ? "https://music.apple.com/us/artist/{$this->apple_music_id}" : null;
    }
}
